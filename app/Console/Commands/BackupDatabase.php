<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Nightly logical backup of the MySQL database.
 *
 * Uses mysqldump (auto-detected on Windows, or set MYSQLDUMP_PATH) and writes
 * a gzipped .sql into storage/app/private/backups, then prunes the oldest
 * files so only BACKUP_KEEP (default 14) remain. The DB password is passed
 * through the MYSQL_PWD environment variable, never on the command line.
 */
class BackupDatabase extends Command
{
    protected $signature = 'pmas:backup
        {--keep= : How many backup files to retain (default from BACKUP_KEEP)}
        {--mysqldump= : Full path to the mysqldump binary}';

    protected $description = 'Back up the database to storage/app/private/backups and prune old backups';

    public function handle(): int
    {
        $connection = config('database.default');

        if (config("database.connections.{$connection}.driver") !== 'mysql') {
            $this->error("pmas:backup only supports MySQL; the \"{$connection}\" connection is not MySQL.");

            return self::FAILURE;
        }

        $binary = $this->resolveMysqldump();
        if (!$binary) {
            $this->error('Could not find mysqldump. Put it on PATH or set MYSQLDUMP_PATH.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');

        $stamp = now()->format('Y-m-d_His');
        $gzipped = function_exists('gzencode');
        $relative = 'backups/pmas-' . $stamp . ($gzipped ? '.sql.gz' : '.sql');

        $config = config("database.connections.{$connection}");
        $password = (string) ($config['password'] ?? '');

        $args = [
            $binary,
            '--host=' . $config['host'],
            '--port=' . $config['port'],
            '--user=' . $config['username'],
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--default-character-set=utf8mb4',
            '--routines',
            '--events',
            (string) $config['database'],
        ];

        $environment = $password !== '' ? ['MYSQL_PWD' => $password] : [];

        try {
            $result = Process::timeout(3600)->env($environment)->run($args);
        } catch (\Throwable $e) {
            $this->error('Backup failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($result->failed()) {
            $this->error('mysqldump failed: ' . trim($result->errorOutput()));

            return self::FAILURE;
        }

        $dump = $result->output();
        if (trim($dump) === '') {
            $this->error('mysqldump produced no output; refusing to write an empty backup.');

            return self::FAILURE;
        }

        $disk->put($relative, $gzipped ? gzencode($dump, 6) : $dump);

        $bytes = $disk->size($relative);
        $this->info(sprintf('Backup written: %s (%.1f KB)', $relative, $bytes / 1024));

        $this->prune((int) ($this->option('keep') ?: config('pmas.backup_keep', 14)));

        return self::SUCCESS;
    }

    protected function resolveMysqldump(): ?string
    {
        $configured = $this->option('mysqldump') ?: config('pmas.mysqldump_path');
        if ($configured) {
            return is_file($configured) ? $configured : $configured; // let Process report a bad path clearly
        }

        // MySQL on Windows installs off PATH; find the newest versioned binary.
        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = glob('C:\\Program Files\\MySQL\\MySQL Server *\\bin\\mysqldump.exe') ?: [];
            rsort($candidates);
            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return 'mysqldump'; // assume it is on PATH
    }

    protected function prune(int $keep): void
    {
        if ($keep < 1) {
            return;
        }

        $disk = Storage::disk('local');
        $files = collect($disk->files('backups'))
            ->filter(fn ($file) => str_ends_with($file, '.sql') || str_ends_with($file, '.sql.gz'))
            ->sortByDesc(fn ($file) => $disk->lastModified($file))
            ->values();

        $stale = $files->slice($keep);

        foreach ($stale as $file) {
            $disk->delete($file);
        }

        if ($stale->isNotEmpty()) {
            $this->line('Pruned ' . $stale->count() . ' old backup(s); keeping ' . $keep . '.');
        }
    }
}
