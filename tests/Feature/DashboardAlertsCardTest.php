<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard alert card.
 *
 * Stock and expiry, monthly bills and supplier debt used to be three separate
 * cards, each with its own heading and its own "see all". One idea - things
 * that need you - spread across three boxes, with nothing shown at all when
 * everything was fine.
 */
class DashboardAlertsCardTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->owner = User::create([
            'name' => 'Owner', 'username' => 'owner', 'email' => 'owner@example.com',
            'password' => 'secret1234', 'role' => 'owner_admin',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_a_clean_dashboard_says_so_instead_of_showing_nothing(): void
    {
        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('All clear')
            ->assertSee('Nothing needs attention right now');
    }

    public function test_only_one_alert_card_renders_whatever_is_wrong(): void
    {
        // A reminder bill due, so the card has something to segment.
        $category = \App\Models\ExpenseCategory::create([
            'name' => 'Rent', 'account_code' => '6000', 'active' => true,
        ]);

        \App\Models\Expense::create([
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $category->id,
            'branch_id' => $this->branch->id,
            'amount' => 6000,
            'monthly_amount' => 6000,
            'payment_method' => 'cash',
            'repeat_monthly' => true,
            'next_due_date' => now()->toDateString(),
            'paid_months' => 1,
        ]);

        $html = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->getContent();

        // One card heading, not three.
        $this->assertSame(1, substr_count($html, 'Things that need you'));
        $this->assertStringNotContainsString('Recent alerts', $html);
        $this->assertStringNotContainsString('Payments coming due', $html);
        $this->assertStringNotContainsString('Keep the shelves moving', $html);

        // Segments are labelled inside the one card.
        $this->assertStringContainsString('Bills to pay', $html);
        $this->assertStringContainsString('6,000.00', $html);

        // Stock and supplier segments are absent because they are empty.
        // ("Owed to suppliers" is also a sidebar label, so assert on the alert
        // copy itself rather than the heading.)
        $this->assertStringNotContainsString('Stock and expiry', $html);
        $this->assertStringNotContainsString('payment overdue', $html);
        $this->assertStringNotContainsString('due within', $html);
    }

    public function test_the_todays_pulse_card_is_gone(): void
    {
        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee("Today's pulse")
            ->assertDontSee('Keep the shelves moving');
    }
}
