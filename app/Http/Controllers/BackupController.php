<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\DatabaseBackupService;
use App\Support\BackupHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner/Admin only. Hands out a complete copy of the database as a download,
 * and keeps the most recent few on the server as a second line of defence.
 *
 * The live site is hosted on a panel with no SSH access and no scheduled tasks,
 * and the panel's own backup feature is limited to paid remote storage, so this
 * screen is the pharmacy's practical way to get its data off the server.
 *
 * The database lives on a different machine from the web server, so a copy
 * held here survives a database failure rather than sharing its fate.
 */
class BackupController extends Controller
{
    /** How many recent dumps to keep on the server. */
    private const KEEP = 5;

    private const DIRECTORY = 'backups';

    public function index(): View
    {
        return view('backups.index', [
            'lastBackupAt' => Setting::value('last_backup_at'),
            'lastBackupBy' => Setting::value('last_backup_by'),
            'warning' => BackupHealth::message(),
            'retained' => $this->retained(),
        ]);
    }

    public function download(DatabaseBackupService $backups): StreamedResponse|RedirectResponse
    {
        // The hosting panel locks max_execution_time to 30s. Raising it is
        // refused there, but on a normal host it gives a growing database
        // room to finish.
        @set_time_limit(180);

        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIRECTORY);

        $filename = 'pmas_' . now()->format('Y-m-d_His') . '.sql';
        $path = $disk->path(self::DIRECTORY . '/' . $filename);

        try {
            $result = $backups->writeTo($path);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'The backup could not be created: ' . $e->getMessage());
        }

        Setting::put('last_backup_at', now()->toDateTimeString());
        Setting::put('last_backup_by', (string) (auth()->user()->username ?? auth()->user()->name ?? ''));

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'backup',
            // Not a database record, so there is no id. The detail that
            // matters is in `changes`.
            'model_type' => 'Backup',
            'model_id' => 0,
            'changes' => [
                'file' => $filename,
                'tables' => $result['tables'],
                'rows' => $result['rows'],
                'kilobytes' => round($result['bytes'] / 1024, 1),
            ],
        ]);

        // Keep this one (pruning keeps the newest, so it survives) and let the
        // browser take a copy as well.
        $this->prune($disk, $filename);

        return $disk->download(self::DIRECTORY . '/' . $filename, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    /**
     * Re-download one of the copies still held on the server - useful if the
     * original download never arrived, or was saved somewhere unhelpful.
     *
     * The name arrives as ?file=... rather than a path segment: see the note
     * on the route. Nothing here may trust it.
     */
    public function fetch(Request $request): StreamedResponse|RedirectResponse
    {
        $name = $this->safeName((string) $request->query('file', ''));

        if ($name === null || !Storage::disk('local')->exists(self::DIRECTORY . '/' . $name)) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'That backup is no longer on the server. Take a new one.');
        }

        return Storage::disk('local')->download(self::DIRECTORY . '/' . $name, $name, [
            'Content-Type' => 'application/sql',
        ]);
    }

    /**
     * The copies held on the server, newest first.
     *
     * @return array<int, array{name:string, bytes:int, taken_at:Carbon, url:string}>
     */
    private function retained(): array
    {
        $disk = Storage::disk('local');

        return collect($this->dumpFiles($disk))
            ->sortByDesc(fn (string $file) => $disk->lastModified($file))
            ->map(function (string $file) use ($disk) {
                $name = basename($file);
                $at = Carbon::createFromTimestamp($disk->lastModified($file));

                return [
                    'name' => $name,
                    'bytes' => (int) $disk->size($file),
                    'taken_at' => $at,
                    'url' => route('backups.fetch', ['file' => $name]),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function dumpFiles($disk): array
    {
        return collect($disk->files(self::DIRECTORY))
            ->filter(fn (string $file) => $this->safeName(basename($file)) !== null)
            ->values()
            ->all();
    }

    /**
     * Delete all but the newest KEEP dumps. $keep is protected so the file
     * being sent to the browser is never the one pruned.
     */
    private function prune($disk, ?string $keep = null): void
    {
        $files = collect($this->dumpFiles($disk))
            ->sortByDesc(fn (string $file) => $disk->lastModified($file))
            ->values();

        $stale = $files->slice(self::KEEP);

        foreach ($stale as $file) {
            if ($keep !== null && basename($file) === $keep) {
                continue;
            }

            $disk->delete($file);
        }
    }

    /**
     * A dump filename, or null if it is anything else.
     *
     * The route parameter is user input, so this is the only thing standing
     * between the URL and the rest of the filesystem.
     */
    private function safeName(string $file): ?string
    {
        // basename() equality rejects any attempt to walk out of the folder.
        if ($file === '' || basename($file) !== $file) {
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9_.-]+\.sql(\.gz)?$/', $file)) {
            return null;
        }

        return $file;
    }
}
