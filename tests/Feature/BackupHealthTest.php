<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use App\Support\BackupHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A backup only protects the pharmacy if somebody remembers to take one.
 *
 * These cover the reminders, which are the part that makes a manual backup
 * work in practice: the owner sees the state as soon as they sign in, on the
 * dashboard and on the sidebar link, without having to think about it.
 */
class BackupHealthTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $owner;
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
        $this->clerk = $make('pharmacist_clerk', 'Clerk');
    }

    // ------------------------------------------------------------------
    // The rule itself
    // ------------------------------------------------------------------

    public function test_a_pharmacy_that_has_never_taken_a_backup_is_warned(): void
    {
        $this->assertNull(BackupHealth::ageInDays());
        $this->assertTrue(BackupHealth::isStale());
        $this->assertStringContainsString('No backup has been taken', (string) BackupHealth::message());
    }

    public function test_a_recent_backup_is_not_warned_about(): void
    {
        Setting::put('last_backup_at', now()->subDays(3)->toDateTimeString());

        $this->assertSame(3, BackupHealth::ageInDays());
        $this->assertFalse(BackupHealth::isStale());
        $this->assertNull(BackupHealth::message());
    }

    public function test_a_backup_taken_today_counts_as_fresh(): void
    {
        Setting::put('last_backup_at', now()->toDateTimeString());

        $this->assertSame(0, BackupHealth::ageInDays());
        $this->assertFalse(BackupHealth::isStale());
    }

    public function test_the_warning_starts_exactly_at_the_threshold(): void
    {
        Setting::put('last_backup_at', now()->subDays(BackupHealth::STALE_AFTER_DAYS - 1)->toDateTimeString());
        $this->assertFalse(BackupHealth::isStale(), 'One day short of the threshold should stay quiet.');

        Setting::put('last_backup_at', now()->subDays(BackupHealth::STALE_AFTER_DAYS)->toDateTimeString());
        $this->assertTrue(BackupHealth::isStale(), 'On the threshold the warning is due.');
        $this->assertStringContainsString('days ago', (string) BackupHealth::message());
    }

    /**
     * A clock that disagrees with the stored value, or a hand-edited setting,
     * must read as "recent" rather than producing a nonsense age or a crash on
     * every page that asks.
     */
    public function test_a_nonsense_or_future_value_does_not_break_it(): void
    {
        Setting::put('last_backup_at', now()->addDays(5)->toDateTimeString());
        $this->assertSame(0, BackupHealth::ageInDays());
        $this->assertFalse(BackupHealth::isStale());

        Setting::put('last_backup_at', 'not a date at all');
        $this->assertNull(BackupHealth::ageInDays());
    }

    // ------------------------------------------------------------------
    // Where the reminder shows up
    // ------------------------------------------------------------------

    public function test_the_dashboard_warns_the_owner_when_the_backup_is_stale(): void
    {
        Setting::put('last_backup_at', now()->subDays(30)->toDateTimeString());

        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Backup reminder')
            ->assertSee('Take a backup');
    }

    public function test_the_dashboard_stays_quiet_when_the_backup_is_recent(): void
    {
        Setting::put('last_backup_at', now()->toDateTimeString());

        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Backup reminder');
    }

    public function test_the_sidebar_marks_the_backups_link_when_stale(): void
    {
        Setting::put('last_backup_at', now()->subDays(30)->toDateTimeString());

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Needs attention');
    }

    public function test_the_sidebar_mark_disappears_once_a_backup_is_taken(): void
    {
        Setting::put('last_backup_at', now()->toDateTimeString());

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Needs attention');
    }

    /**
     * A clerk cannot take a backup, so telling them about it would only be a
     * dead end.
     */
    public function test_a_clerk_is_not_shown_backup_reminders(): void
    {
        Setting::put('last_backup_at', now()->subDays(30)->toDateTimeString());

        $this->actingAs($this->clerk)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Backup reminder')
            ->assertDontSee('Needs attention');
    }
}
