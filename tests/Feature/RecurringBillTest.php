<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Monthly bill reminders.
 *
 * Recording a bill that repeats moves the existing reminder on instead of
 * starting a second one, and paying several months at once (rent is often
 * paid a quarter or a half-year ahead) moves it by that many months.
 */
class RecurringBillTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected Branch $branch;
    protected ExpenseCategory $rent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);
        $this->seed(\Database\Seeders\ExpenseCategorySeeder::class);

        // Monthly arithmetic is the subject here; a mid-month clock keeps the
        // expectations stable on month-end days.
        Carbon::setTestNow(Carbon::create(2026, 3, 15, 10, 0, 0));

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->owner = User::create([
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => 'secret1234',
            'role' => 'owner_admin',
            'branch_id' => $this->branch->id,
        ]);
        $this->rent = ExpenseCategory::where('name', 'Rent')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function record(array $overrides = [])
    {
        return $this->actingAs($this->owner)->post(route('expenses.store'), array_merge([
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $this->rent->id,
            'amount' => 4000,
            'payment_method' => 'cash',
            'repeat_monthly' => 1,
            'paid_months' => 1,
        ], $overrides));
    }

    public function test_a_new_repeating_bill_gets_a_due_date_one_month_out(): void
    {
        $this->record()->assertSessionHasNoErrors();

        $expense = Expense::firstOrFail();
        $this->assertTrue((bool) $expense->repeat_monthly);
        $this->assertSame(now()->addMonthNoOverflow()->toDateString(), $expense->next_due_date->toDateString());
    }

    public function test_paying_a_bill_moves_the_reminder_on_and_does_not_double_it(): void
    {
        $this->record(['expense_date' => now()->subMonthNoOverflow()->toDateString()]);
        $first = Expense::firstOrFail();
        $this->assertSame(now()->toDateString(), $first->next_due_date->toDateString());

        // Pay it. The same category is a payment for the existing bill, so the
        // reminder moves on rather than a second reminder being created.
        $this->record(['expense_date' => now()->toDateString()])->assertSessionHasNoErrors();

        $this->assertSame(2, Expense::count());

        $active = Expense::where('repeat_monthly', true)->whereNotNull('next_due_date')->get();
        $this->assertCount(1, $active, 'Only one reminder per bill.');
        $this->assertSame(
            now()->addMonthNoOverflow()->toDateString(),
            $active->first()->next_due_date->toDateString()
        );

        // The old row is kept for the books but no longer reminds.
        $this->assertFalse((bool) $first->fresh()->repeat_monthly);
        $this->assertNull($first->fresh()->next_due_date);
    }

    public function test_paying_six_months_in_advance_moves_the_reminder_six_months(): void
    {
        $this->record(['expense_date' => now()->subMonthNoOverflow()->toDateString()]);

        $this->record([
            'expense_date' => now()->toDateString(),
            'paid_months' => 6,
        ])->assertSessionHasNoErrors();

        $active = Expense::where('repeat_monthly', true)->whereNotNull('next_due_date')->firstOrFail();
        $this->assertSame(
            now()->addMonthsNoOverflow(6)->toDateString(),
            $active->next_due_date->toDateString()
        );
    }

    public function test_paying_three_months_records_the_total_and_keeps_the_monthly_rate(): void
    {
        // Rent is 6,000 a month and three months are paid at once.
        $this->record(['expense_date' => now()->subMonthNoOverflow()->toDateString()]);

        $this->record([
            'expense_date' => now()->toDateString(),
            'amount' => 6000,
            'paid_months' => 3,
        ])->assertSessionHasNoErrors();

        $payment = Expense::orderByDesc('id')->firstOrFail();

        // The books record what actually left the account: 3 x 6,000.
        $this->assertEquals(18000.0, (float) $payment->amount);
        $this->assertEquals(3, $payment->paid_months);

        // ...but the reminder still quotes the monthly rate.
        $this->assertEquals(6000.0, (float) $payment->monthly_amount);
        $this->assertEquals(6000.0, $payment->reminderAmount());

        // And the journal entry carries the same total.
        $entry = \App\Models\JournalEntry::where('reference_type', 'expense')
            ->where('reference_id', $payment->id)->firstOrFail();
        $this->assertEquals(18000.0, (float) $entry->total_debit);
    }

    public function test_a_single_month_payment_is_not_multiplied(): void
    {
        $this->record(['expense_date' => now()->subMonthNoOverflow()->toDateString()]);
        $this->record(['expense_date' => now()->toDateString(), 'amount' => 6000, 'paid_months' => 1])
            ->assertSessionHasNoErrors();

        $payment = Expense::orderByDesc('id')->firstOrFail();
        $this->assertEquals(6000.0, (float) $payment->amount);
    }

    public function test_a_one_off_expense_never_touches_a_repeating_bill(): void
    {
        $this->record(['expense_date' => now()->subMonthNoOverflow()->toDateString()]);
        $before = Expense::where('repeat_monthly', true)->whereNotNull('next_due_date')->firstOrFail();

        // Same category, but NOT marked as repeating: this is a one-off.
        $this->record([
            'expense_date' => now()->toDateString(),
            'repeat_monthly' => 0,
            'paid_months' => 1,
        ])->assertSessionHasNoErrors();

        $after = Expense::where('repeat_monthly', true)->whereNotNull('next_due_date')->firstOrFail();
        $this->assertSame($before->id, $after->id);
        $this->assertSame(
            $before->next_due_date->toDateString(),
            $after->next_due_date->toDateString(),
            'A one-off must not move the reminder.'
        );
    }

    public function test_a_due_bill_shows_on_the_alerts_page_with_a_prefilled_pay_link(): void
    {
        $this->record(['expense_date' => now()->subMonthNoOverflow()->toDateString()]);
        $bill = Expense::firstOrFail();

        $html = $this->actingAs($this->owner)->get(route('alerts.index'))->content();

        $this->assertStringContainsString('Monthly bills due now', $html);
        $this->assertStringContainsString('Rent', $html);
        $this->assertStringContainsString(route('expenses.create', ['bill' => $bill->id]), $html);
    }

    public function test_the_pay_link_opens_a_prefilled_form(): void
    {
        $this->record(['expense_date' => now()->subMonthNoOverflow()->toDateString()]);
        $bill = Expense::firstOrFail();

        $html = $this->actingAs($this->owner)->get(route('expenses.create', ['bill' => $bill->id]))->content();

        $this->assertStringContainsString('Paying:', $html);
        $this->assertStringContainsString('Rent', $html);
        $this->assertStringContainsString('name="bill" value="' . $bill->id . '"', $html);
    }

    public function test_the_alerts_link_carries_a_badge_count(): void
    {
        $this->record(['expense_date' => now()->subMonthNoOverflow()->toDateString()]);

        $html = $this->actingAs($this->owner)->get(route('dashboard'))->content();

        // The badge on the Alerts link, and the dashboard card.
        $this->assertStringContainsString('Bills to pay', $html);
        $this->assertStringContainsString('Rent', $html);
    }

    public function test_an_overdue_bill_is_flagged(): void
    {
        $this->record(['expense_date' => now()->subMonthNoOverflow()->toDateString()]);
        $bill = Expense::firstOrFail();
        $bill->update(['next_due_date' => now()->subMonthsNoOverflow(3)->toDateString()]);

        $this->assertTrue($bill->fresh()->isOverdue());

        $html = $this->actingAs($this->owner)->get(route('alerts.index'))->content();
        $this->assertStringContainsString('Overdue', $html);
    }

    public function test_a_clerk_does_not_see_monthly_bills(): void
    {
        $clerk = User::create([
            'name' => 'Clerk', 'username' => 'clerk', 'email' => 'clerk@example.com',
            'password' => 'secret1234', 'role' => 'pharmacist_clerk', 'branch_id' => $this->branch->id,
        ]);

        $this->record(['expense_date' => now()->subMonthNoOverflow()->toDateString()]);

        $html = $this->actingAs($clerk)->get(route('alerts.index'))->content();
        $this->assertStringNotContainsString('Monthly bills due now', $html);
    }
}
