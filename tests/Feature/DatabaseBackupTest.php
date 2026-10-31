<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The pharmacy must be able to get its own data off the server.
 *
 * The production host gives no SSH, no scheduled tasks, and gates its own
 * backup feature behind paid remote storage, so this screen is the practical
 * way out. It is Owner/Admin only because the dump holds every staff account
 * (password hashes included) and the whole business history.
 */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $owner;
    protected User $manager;
    protected User $clerk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Main Branch']);

        $make = fn (string $role, string $name) => User::create([
            'name' => $name,
            'username' => strtolower($name),
            'email' => strtolower($name) . '@example.com',
            'password' => 'secret1234',
            'role' => $role,
            'branch_id' => $this->branch->id,
        ]);

        $this->owner = $make('owner_admin', 'Owner');
        $this->manager = $make('branch_manager', 'Manager');
        $this->clerk = $make('pharmacist_clerk', 'Clerk');
    }

    protected function tearDown(): void
    {
        // The backup download raises PHP's execution limit to 180s. That is
        // process-wide, so restore it before the rest of the suite runs.
        @set_time_limit(0);

        // A download that is never "sent" (as in these tests) leaves its file
        // behind, because deleteFileAfterSend only fires on a real send.
        foreach (glob(storage_path('app/private/backups/*.sql')) ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_backup_command_is_registered(): void
    {
        $this->assertArrayHasKey(
            'pmas:backup',
            Artisan::all()
        );
    }

    /**
     * The backup shells out to mysqldump, which only applies to MySQL. On any
     * other connection it must fail closed rather than write a bogus file.
     */
    public function test_backup_refuses_non_mysql_connections(): void
    {
        $driver = config('database.connections.' . config('database.default') . '.driver');

        if ($driver === 'mysql') {
            $this->markTestSkipped('Guard under test only applies to non-MySQL connections.');
        }

        $this->artisan('pmas:backup')->assertExitCode(1);
    }

    // ------------------------------------------------------------------
    // Who may take a backup
    // ------------------------------------------------------------------

    public function test_the_owner_can_open_the_backup_screen(): void
    {
        $this->actingAs($this->owner)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertSee('Download backup now');
    }

    public function test_an_owner_can_download_a_backup_file(): void
    {
        $response = $this->actingAs($this->owner)->get(route('backups.download'));

        $response->assertOk();

        // assertDownload() wants the exact filename, and this one is stamped
        // with the time, so check the shape of the header instead.
        $disposition = (string) $response->headers->get('content-disposition');

        $this->assertMatchesRegularExpression(
            '/attachment; filename=pmas_\d{4}-\d{2}-\d{2}_\d{6}\.sql/',
            $disposition
        );
    }

    public function test_a_branch_manager_cannot_reach_the_backup_screen(): void
    {
        // A manager runs the shop; they do not walk out with the accounts.
        $this->actingAs($this->manager)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($this->manager)->get(route('backups.download'))->assertForbidden();
    }

    public function test_a_clerk_cannot_reach_the_backup_screen(): void
    {
        $this->actingAs($this->clerk)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($this->clerk)->get(route('backups.download'))->assertForbidden();
    }

    public function test_the_sidebar_link_only_appears_for_the_owner(): void
    {
        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('backups.index'));

        $this->actingAs($this->manager)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('backups.index'));

        $this->actingAs($this->clerk)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('backups.index'));
    }

    // ------------------------------------------------------------------
    // What comes out of it
    // ------------------------------------------------------------------

    /**
     * Unlike pmas:backup (which shells out to mysqldump), the button's dumper
     * is pure PHP so it still works where exec() is unavailable. This test
     * pins the shape of what it writes.
     */
    public function test_the_dump_contains_the_structure_and_the_data(): void
    {
        $path = storage_path('app/private/backups/test-dump.sql');
        @mkdir(dirname($path), 0775, true);

        $result = app(DatabaseBackupService::class)->writeTo($path);
        $sql = (string) file_get_contents($path);

        $this->assertGreaterThan(0, $result['tables'], 'Every table should be dumped.');
        $this->assertGreaterThan(0, $result['rows'], 'The dumped tables should not all be empty.');

        // Restorable shape: drop first, then recreate, then insert.
        $this->assertStringContainsString('DROP TABLE IF EXISTS', $sql);
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('INSERT INTO', $sql);

        // The actual data, not just the empty schema.
        $this->assertStringContainsString('Main Branch', $sql);
        $this->assertStringContainsString('owner@example.com', $sql);

        // A dump that has to be imported in one specific table order is a
        // trap; foreign key checks must be deferred around it.
        if ($result['driver'] === 'mysql') {
            $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS = 0', $sql);
            $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS = 1', $sql);
        }

        // Password hashes belong in a backup, but never in plain text.
        $this->assertStringNotContainsString('secret1234', $sql);

        @unlink($path);
    }

    public function test_the_page_remembers_when_the_last_backup_was_taken(): void
    {
        $this->actingAs($this->owner)->get(route('backups.download'))->assertOk();

        $this->assertNotNull(Setting::value('last_backup_at'));
        $this->assertSame('owner', Setting::value('last_backup_by'));

        $this->actingAs($this->owner)->get(route('backups.index'))
            ->assertOk()
            ->assertSee(Setting::value('last_backup_at'));
    }

    /**
     * Taking a copy of every account and every sale is exactly the kind of
     * thing that should leave a trace.
     */
    public function test_taking_a_backup_is_written_to_the_activity_log(): void
    {
        $this->actingAs($this->owner)->get(route('backups.download'))->assertOk();

        $log = ActivityLog::query()->where('action', 'backup')->first();

        $this->assertNotNull($log, 'A backup should be recorded in the activity log.');
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertSame('Backup', $log->model_type);
        $this->assertArrayHasKey('file', $log->changes);
        $this->assertGreaterThan(0, $log->changes['tables']);

        // And it reads as English on the log screen, not as a raw row.
        $this->actingAs($this->owner)
            ->get(route('activity-logs.index'))
            ->assertOk()
            ->assertSee('Database backup');
    }

    public function test_the_download_is_not_a_page_that_leaks_the_dump_into_html(): void
    {
        $response = $this->actingAs($this->owner)->get(route('backups.download'));

        // Sent as a file, not rendered as a web page.
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_backups_are_written_outside_the_public_folder(): void
    {
        // Anything under public/ is served to the world by the web server.
        $disk = Storage::disk('local');

        $this->assertStringNotContainsString(
            DIRECTORY_SEPARATOR . 'public',
            $disk->path('backups'),
            'Backups must never be reachable over the web.'
        );
    }

    // ------------------------------------------------------------------
    // Copies kept on the server
    // ------------------------------------------------------------------

    public function test_the_server_keeps_only_the_most_recent_few_backups(): void
    {
        $dir = storage_path('app/private/backups');
        @mkdir($dir, 0775, true);

        // Seven older dumps, one per day, oldest first.
        for ($day = 1; $day <= 7; $day++) {
            $path = $dir . '/pmas_2026-01-0' . $day . '_120000.sql';
            file_put_contents($path, "-- an older backup\n");
            touch($path, now()->subDays(10 - $day)->getTimestamp());
        }

        // The oldest are the ones that should go.
        $oldest = $dir . '/pmas_2026-01-01_120000.sql';
        $newest = $dir . '/pmas_2026-01-07_120000.sql';

        $this->assertCount(7, glob($dir . '/*.sql'));

        $this->actingAs($this->owner)->get(route('backups.download'))->assertOk();

        // Five kept: the four newest of the old ones plus the one just taken.
        $this->assertCount(5, glob($dir . '/*.sql'));
        $this->assertFileDoesNotExist($oldest, 'The oldest backup should be cleared out.');
        $this->assertFileExists($newest, 'Recent backups must survive the tidy-up.');
    }

    /**
     * The host fronts the site with nginx, which returns its own 403 for any
     * request whose *path* ends in ".sql" - long before the application sees
     * it. A re-download link built as "/backups/pmas_....sql" therefore
     * looked perfectly correct and could never have worked, and the failure
     * would have looked like a permissions problem.
     */
    public function test_the_redownload_link_carries_the_filename_outside_the_path(): void
    {
        $url = route('backups.fetch', ['file' => 'pmas_2026-01-01_120000.sql']);
        $path = parse_url($url, PHP_URL_PATH);

        $this->assertStringNotContainsString('.sql', $path, 'A .sql path is blocked by the web server.');
        $this->assertStringContainsString('file=', $url, 'The filename has to travel in the query string.');
    }

    public function test_the_owner_can_download_a_copy_kept_on_the_server(): void
    {
        $this->actingAs($this->owner)->get(route('backups.download'))->assertOk();

        $name = basename(glob(storage_path('app/private/backups/*.sql'))[0]);

        $this->actingAs($this->owner)
            ->get(route('backups.fetch', ['file' => $name]))
            ->assertOk()
            ->assertDownload($name);
    }

    public function test_the_kept_copies_are_listed_on_the_page(): void
    {
        $this->actingAs($this->owner)->get(route('backups.download'))->assertOk();

        $this->actingAs($this->owner)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertSee('Copies kept on the server');
    }

    /**
     * The route parameter is user input. A file sitting in the same folder
     * that is not a dump must be neither served nor advertised.
     */
    public function test_it_will_not_hand_out_anything_but_a_dump(): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');
        $disk->put('backups/secret.txt', 'this is not a backup');

        $this->actingAs($this->owner)
            ->get(route('backups.fetch', ['file' => 'secret.txt']))
            ->assertRedirect(route('backups.index'));

        $this->actingAs($this->owner)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertDontSee('secret.txt');
    }

    public function test_a_backup_that_is_gone_is_reported_rather_than_served(): void
    {
        $this->actingAs($this->owner)
            ->get(route('backups.fetch', ['file' => 'pmas_2020-01-01_000000.sql']))
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('error');
    }

    public function test_a_traversal_attempt_does_not_reach_outside_the_folder(): void
    {
        // The route only matches a bare filename, so a path cannot even get
        // as far as the controller.
        $this->actingAs($this->owner)
            ->get('/backups/..%2F.env')
            ->assertNotFound();
    }

    public function test_a_clerk_cannot_download_a_kept_backup(): void
    {
        $this->actingAs($this->owner)->get(route('backups.download'))->assertOk();

        $name = basename(glob(storage_path('app/private/backups/*.sql'))[0]);

        $this->actingAs($this->clerk)
            ->get(route('backups.fetch', ['file' => $name]))
            ->assertForbidden();
    }
}
