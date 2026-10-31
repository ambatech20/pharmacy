<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard shortcuts: the "+" that does something.
 *
 * Shortcuts are personal. One person removing "Record purchase" must not
 * remove it for the owner standing at the next till, and nobody should be able
 * to save a shortcut to a screen their role cannot open.
 */
class DashboardShortcutsTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $clerk;
    protected User $owner;

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

        $this->clerk = $make('pharmacist_clerk', 'Clerk');
        $this->owner = $make('owner_admin', 'Owner');
    }

    public function test_the_defaults_are_the_three_daily_tasks(): void
    {
        $this->actingAs($this->clerk)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Start a sale')
            ->assertSee('Record purchase')
            ->assertSee('Adjust stock');
    }

    public function test_a_shortcut_can_be_added(): void
    {
        $this->actingAs($this->clerk)
            ->post(route('dashboard.shortcuts.update'), ['shortcuts' => ['sale', 'purchase', 'adjust', 'customer']])
            ->assertRedirect(route('dashboard'));

        $this->assertEqualsCanonicalizing(
            ['sale', 'purchase', 'adjust', 'customer'],
            $this->clerk->fresh()->dashboard_shortcuts
        );

        $this->actingAs($this->clerk)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Add customer');
    }

    public function test_a_shortcut_can_be_removed_with_one_click(): void
    {
        $this->actingAs($this->clerk)
            ->post(route('dashboard.shortcuts.update'), ['shortcuts' => ['sale', 'purchase', 'adjust', 'customer']]);

        $this->actingAs($this->clerk)
            ->post(route('dashboard.shortcuts.remove'), ['shortcut' => 'customer'])
            ->assertRedirect(route('dashboard'));

        $this->assertEqualsCanonicalizing(
            ['sale', 'purchase', 'adjust'],
            $this->clerk->fresh()->dashboard_shortcuts
        );
    }

    public function test_shortcuts_can_be_emptied_and_reset(): void
    {
        $this->actingAs($this->clerk)
            ->post(route('dashboard.shortcuts.update'), ['shortcuts' => []])
            ->assertRedirect(route('dashboard'));

        $this->assertSame([], $this->clerk->fresh()->dashboard_shortcuts);

        $this->actingAs($this->clerk)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No shortcuts yet');

        $this->actingAs($this->clerk)
            ->post(route('dashboard.shortcuts.reset'))
            ->assertRedirect(route('dashboard'));

        $this->assertNull($this->clerk->fresh()->dashboard_shortcuts, 'Null falls back to the defaults.');
        $this->actingAs($this->clerk)->get(route('dashboard'))->assertOk()->assertSee('Start a sale');
    }

    public function test_shortcuts_are_personal(): void
    {
        $this->actingAs($this->clerk)
            ->post(route('dashboard.shortcuts.update'), ['shortcuts' => ['sale']]);

        // The owner keeps their own.
        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Record purchase');
    }

    public function test_a_clerk_is_not_offered_the_owner_only_shortcuts(): void
    {
        $this->actingAs($this->clerk)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Record expense');

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Record expense');
    }

    public function test_a_forged_post_cannot_plant_a_shortcut_the_role_cannot_open(): void
    {
        // "expense" and "reports" are owner/manager only. A clerk posting them
        // directly must be filtered out server-side, not just hidden in the UI.
        $this->actingAs($this->clerk)
            ->post(route('dashboard.shortcuts.update'), ['shortcuts' => ['sale', 'expense', 'reports', 'not-a-real-key']])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(['sale'], $this->clerk->fresh()->dashboard_shortcuts);

        $this->actingAs($this->clerk)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Record expense')
            ->assertDontSee('Sales report');
    }

    public function test_a_shortcut_saved_by_a_promoted_user_who_then_demoted_is_dropped(): void
    {
        // The owner saves everything, then loses the role. Their dashboard must
        // not keep dead links to screens that would 403.
        $this->actingAs($this->owner)
            ->post(route('dashboard.shortcuts.update'), ['shortcuts' => ['sale', 'expense', 'reports']]);

        $this->owner->update(['role' => 'pharmacist_clerk']);

        $this->actingAsWithoutShift($this->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Start a sale')
            ->assertDontSee('Record expense');
    }

    public function test_a_saved_key_that_no_longers_exists_is_dropped(): void
    {
        $this->clerk->dashboard_shortcuts = ['sale', 'a_shortcut_we_deleted_last_year'];
        $this->clerk->save();

        $this->actingAs($this->clerk)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Start a sale')
            ->assertDontSee('a_shortcut_we_deleted_last_year');
    }

    public function test_every_available_shortcut_points_somewhere_real(): void
    {
        // A shortcut to a renamed route would be a dead tile on the dashboard.
        foreach (\App\Http\Controllers\DashboardController::AVAILABLE_SHORTCUTS as $key => $shortcut) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has($shortcut['route']),
                "Shortcut '{$key}' points at route '{$shortcut['route']}', which does not exist."
            );
        }
    }
}
