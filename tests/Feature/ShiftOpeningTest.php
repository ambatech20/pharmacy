<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\ShiftOpening;
use App\Models\TillClosing;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The drawer count that starts every shift.
 *
 * Two things are being guaranteed here. First, nobody walks into the app
 * without saying what was in the till. Second - the reason that matters - the
 * end-of-shift variance is measured from that count, so a normal day with a
 * float in the drawer no longer reports the whole float as a shortage.
 */
class ShiftOpeningTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $clerk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->clerk = User::create([
            'name' => 'Clerk',
            'username' => 'clerk',
            'email' => 'clerk@example.com',
            'password' => 'secret1234',
            'role' => 'pharmacist_clerk',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_nobody_reaches_a_screen_before_counting_the_drawer(): void
    {
        $this->actingAsWithoutShift($this->clerk)
            ->get(route('dashboard'))
            ->assertRedirect(route('shift.open'));

        // Not just the dashboard - every screen.
        $this->actingAsWithoutShift($this->clerk)
            ->get(route('dispense.create'))
            ->assertRedirect(route('shift.open'));
    }

    public function test_counting_the_drawer_lets_them_carry_on(): void
    {
        $this->actingAsWithoutShift($this->clerk)
            ->get(route('shift.open'))
            ->assertOk()
            ->assertSee('Count the drawer');

        $this->actingAsWithoutShift($this->clerk)
            ->post(route('shift.store'), ['opening_cash' => 2500])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('shift_openings', [
            'user_id' => $this->clerk->id,
            'branch_id' => $this->branch->id,
            'opening_cash' => 2500,
            'closed_at' => null,
        ]);

        $this->actingAs($this->clerk)->get(route('dashboard'))->assertOk();
    }

    public function test_an_empty_drawer_is_a_valid_answer(): void
    {
        $this->actingAsWithoutShift($this->clerk)
            ->post(route('shift.store'), ['opening_cash' => 0])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('shift_openings', [
            'user_id' => $this->clerk->id,
            'opening_cash' => 0,
        ]);
    }

    public function test_a_number_cannot_be_skipped(): void
    {
        $this->actingAsWithoutShift($this->clerk)
            ->post(route('shift.store'), [])
            ->assertSessionHasErrors('opening_cash');

        $this->assertSame(0, ShiftOpening::count());

        // Still standing at the gate.
        $this->actingAsWithoutShift($this->clerk)
            ->get(route('dashboard'))
            ->assertRedirect(route('shift.open'));
    }

    public function test_a_negative_count_is_refused(): void
    {
        $this->actingAsWithoutShift($this->clerk)
            ->post(route('shift.store'), ['opening_cash' => -50])
            ->assertSessionHasErrors('opening_cash');

        $this->assertSame(0, ShiftOpening::count());
    }

    public function test_they_are_only_asked_once_per_shift(): void
    {
        $this->actingAsWithoutShift($this->clerk)
            ->post(route('shift.store'), ['opening_cash' => 1000]);

        // Still on the shift, so no second prompt - not on the dashboard, not
        // on the till close.
        $this->actingAsWithoutShift($this->clerk)->get(route('dashboard'))->assertOk();
        $this->actingAsWithoutShift($this->clerk)->get(route('till-closings.index'))->assertOk();

        $this->assertSame(1, ShiftOpening::count());
    }

    public function test_closing_the_till_ends_the_shift_and_asks_again_next_login(): void
    {
        $this->actingAsWithoutShift($this->clerk)
            ->post(route('shift.store'), ['opening_cash' => 1000]);

        $this->actingAsWithoutShift($this->clerk)
            ->post(route('till-closings.store'), ['counted_cash' => 1000])
            ->assertRedirect(route('till-closings.index'));

        $this->assertNotNull(ShiftOpening::firstOrFail()->closed_at);
        $this->assertSame(
            TillClosing::firstOrFail()->id,
            (int) ShiftOpening::firstOrFail()->till_closing_id
        );

        // Next login is a new shift, so the question comes back.
        $this->actingAsWithoutShift($this->clerk)
            ->get(route('dashboard'))
            ->assertRedirect(route('shift.open'));
    }

    public function test_the_count_is_remembered_not_posted_to_the_accounts(): void
    {
        // The float is already in the cash account from wherever it came from.
        // Posting it again every morning would double-count it forever, so
        // this screen must leave the ledger alone.
        $this->actingAsWithoutShift($this->clerk)
            ->post(route('shift.store'), ['opening_cash' => 40000]);

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('ledgers', 0);
    }

    public function test_a_correct_day_with_a_float_reports_no_shortage(): void
    {
        // The bug this fixes: the drawer started with 5,000, nothing was sold
        // in cash, and the drawer still holds 5,000. That is a perfect day -
        // it used to be reported as a 5,000 shortage, every single time.
        $this->actingAsWithoutShift($this->clerk)
            ->post(route('shift.store'), ['opening_cash' => 5000]);

        $this->actingAsWithoutShift($this->clerk)
            ->post(route('till-closings.store'), ['counted_cash' => 5000])
            ->assertRedirect(route('till-closings.index'));

        $closing = TillClosing::firstOrFail();

        $this->assertEquals(5000.00, (float) $closing->expected_cash, 'Expected = the float you started with.');
        $this->assertEquals(0.0, (float) $closing->variance, 'A normal day is not a shortage.');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_a_real_shortage_is_still_caught_against_the_float(): void
    {
        $this->actingAsWithoutShift($this->clerk)
            ->post(route('shift.store'), ['opening_cash' => 5000]);

        // 200 genuinely missing.
        $this->actingAsWithoutShift($this->clerk)
            ->post(route('till-closings.store'), ['counted_cash' => 4800]);

        $this->assertEquals(-200.00, (float) TillClosing::firstOrFail()->variance);

        $cash = Account::where('code', '1001')->firstOrFail();
        $overShort = Account::where('code', '5200')->firstOrFail();

        $this->assertEquals(200.00, (float) $cash->ledgers()->sum('credit'));
        $this->assertEquals(200.00, (float) $overShort->ledgers()->sum('debit'));
    }

    public function test_the_till_page_shows_the_count_it_is_measuring_from(): void
    {
        $this->actingAsWithoutShift($this->clerk)
            ->post(route('shift.store'), ['opening_cash' => 7500]);

        $this->actingAsWithoutShift($this->clerk)->get(route('till-closings.index'))
            ->assertOk()
            ->assertSee('Cash you started this shift with')
            ->assertSee('7,500.00');
    }

    public function test_signing_out_is_always_reachable(): void
    {
        // A prompt you cannot escape is a way to lock staff out of the till.
        $this->actingAsWithoutShift($this->clerk)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_being_interrupted_lands_back_on_the_page_they_wanted(): void
    {
        $this->actingAsWithoutShift($this->clerk)
            ->get(route('purchases.create'))
            ->assertRedirect(route('shift.open'));

        $this->actingAsWithoutShift($this->clerk)
            ->withSession(['intended_url' => '/purchases/create'])
            ->post(route('shift.store'), ['opening_cash' => 100])
            ->assertRedirect('/purchases/create');
    }

    public function test_a_crafted_redirect_is_ignored(): void
    {
        // The destination comes back through the session, so it is still input.
        foreach (['//evil.example', 'https://evil.example', '/\\evil.example'] as $target) {
            $this->actingAsWithoutShift($this->clerk)
                ->withSession(['intended_url' => $target])
                ->post(route('shift.store'), ['opening_cash' => 100])
                ->assertRedirect(route('dashboard'));
        }
    }

    public function test_two_staff_can_be_on_shift_at_the_same_time(): void
    {
        // Each cashier counts their own drawer; they do not share one shift.
        $second = User::create([
            'name' => 'Second', 'username' => 'second', 'email' => 'second@example.com',
            'password' => 'secret1234', 'role' => 'pharmacist_clerk',
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAsWithoutShift($this->clerk)->post(route('shift.store'), ['opening_cash' => 100]);
        $this->actingAsWithoutShift($second)->post(route('shift.store'), ['opening_cash' => 250]);

        $this->assertSame(2, ShiftOpening::count());
        $this->assertSame(2, ShiftOpening::open()->count());
    }

    public function test_a_double_submit_does_not_start_two_shifts(): void
    {
        // Two tabs, or an impatient double tap.
        $this->actingAsWithoutShift($this->clerk)->post(route('shift.store'), ['opening_cash' => 100]);
        $this->actingAsWithoutShift($this->clerk)->post(route('shift.store'), ['opening_cash' => 999]);

        $this->assertSame(1, ShiftOpening::count());
        $this->assertEquals(100.00, (float) ShiftOpening::firstOrFail()->opening_cash);
    }

    public function test_the_opening_balances_screen_is_gone(): void
    {
        // It asked for eight figures the books could not act on. Cash is now
        // asked per shift; the rest are recorded where they belong.
        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(fn ($route) => $route->getName() === 'opening-balances.edit')
        );
    }

    public function test_the_dashboard_offers_end_shift_while_a_shift_is_open(): void
    {
        // Ending a shift is the last thing you do in a day, so it belongs on
        // the dashboard rather than buried in the sidebar.
        $this->openShiftFor($this->clerk, 3200);

        $this->actingAsWithoutShift($this->clerk)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('End shift')
            ->assertSee(route('till-closings.index'));
    }

    public function test_the_button_leads_to_a_page_that_says_end_shift(): void
    {
        // "No button without a shift" cannot be tested through the browser: with
        // no open shift the gate redirects to the drawer count before the
        // dashboard is ever rendered. The @if is belt-and-braces.
        $this->openShiftFor($this->clerk, 100);

        $this->actingAsWithoutShift($this->clerk)->get(route('till-closings.index'))
            ->assertOk()
            ->assertSee('End shift')
            ->assertSee('Recording this closes your shift');
    }

    public function test_the_button_is_small_and_carries_no_caption(): void
    {
        // "Started 8:15 AM with ETB 4,250.00" was clutter on a button. Those
        // figures belong on the page it opens, not on the way to it.
        $this->openShiftFor($this->clerk, 4250);

        $html = $this->actingAsWithoutShift($this->clerk)->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*\/till-closings"[^>]*>\s*End shift\s*<\/a>/',
            $html,
            'The button should carry the label and nothing else.'
        );

        $this->assertStringNotContainsString('Started', $html);
    }

    public function test_till_close_is_no_longer_in_the_operations_menu(): void
    {
        $this->openShiftFor($this->clerk, 100);

        $this->actingAsWithoutShift($this->clerk)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Till Close', false);
    }
}
