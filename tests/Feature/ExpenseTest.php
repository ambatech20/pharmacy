<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * These tests are about monthly arithmetic, not about today's date. Pin the
     * clock to a mid-month day so a month-end run cannot turn "one month ago"
     * into a different day of the month.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 3, 15, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function owner(): User
    {
        $user = User::factory()->create(['role' => 'owner_admin', 'branch_id' => \App\Models\Branch::create(['name' => 'Main', 'address' => 'Addis', 'phone' => '0911'])->id]);
        $this->seed(\Database\Seeders\AccountSeeder::class);
        $this->seed(\Database\Seeders\ExpenseCategorySeeder::class);

        return $user;
    }

    protected function category(string $name): ExpenseCategory
    {
        return ExpenseCategory::where('name', $name)->firstOrFail();
    }

    public function test_expense_posts_balanced_entry_expense_account_dr_method_acct_cr(): void
    {
        $owner = $this->owner();
        $cat = $this->category('Rent');
        $expenseAccount = Account::where('code', $cat->account_code)->firstOrFail();
        $cashAccount = Account::where('code', Payment::ACCOUNT_CODES['cash'])->firstOrFail();

        $resp = $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $cat->id,
            'amount' => 500,
            'payment_method' => 'cash',
            'note' => 'October rent',
        ]);
        $resp->assertRedirect(route('expenses.index'));

        $expense = Expense::firstOrFail();
        $this->assertEquals(500.0, (float) $expense->amount);

        $entry = JournalEntry::where('reference_type', 'expense')->where('reference_id', $expense->id)->firstOrFail();
        // Debits == credits (balanced).
        $lines = $entry->ledgers;
        $this->assertEquals(
            (float) $lines->sum('debit'),
            (float) $lines->sum('credit')
        );
        $this->assertEqualsWithDelta(500.0, (float) $lines->sum('debit'), 0.001);

        // Expense account increased (debit), cash reduced (credit).
        $expLine = $lines->firstWhere('account_id', $expenseAccount->id);
        $this->assertNotNull($expLine);
        $this->assertEqualsWithDelta(500.0, (float) $expLine->debit, 0.001);
        $cashLine = $lines->first(fn ($l) => $l->account_id === $cashAccount->id);
        $this->assertNotNull($cashLine);
        $this->assertEqualsWithDelta(500.0, (float) $cashLine->credit, 0.001);
    }

    public function test_deleting_expense_reverses_and_soft_deletes(): void
    {
        $owner = $this->owner();
        $cat = $this->category('Electricity');
        $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $cat->id,
            'amount' => 120,
            'payment_method' => 'cash',
        ]);
        $expense = Expense::firstOrFail();

        $this->actingAs($owner)->delete(route('expenses.destroy', $expense->id))->assertRedirect();

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
        // A reversal entry exists in addition to the original.
        $this->assertDatabaseHas('journal_entries', ['reference_type' => 'expense', 'reference_id' => $expense->id]);
        $this->assertDatabaseHas('journal_entries', ['reference_type' => 'expense_reversal', 'reference_id' => $expense->id]);
    }

    public function test_clerk_and_branch_manager_cannot_access_expenses(): void
    {
        $owner = $this->owner();
        $clerk = User::factory()->create(['role' => 'pharmacist_clerk']);
        $manager = User::factory()->create(['role' => 'branch_manager']);

        $this->actingAs($clerk)->get(route('expenses.index'))->assertForbidden();
        $this->actingAs($clerk)->post(route('expenses.store'), [])->assertForbidden();
        $this->actingAs($manager)->get(route('expenses.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('expenses.index'))->assertOk();
    }

    public function test_category_rename_and_deactivate(): void
    {
        $owner = $this->owner();
        $cat = $this->category('Water');

        $this->actingAs($owner)->put(route('expense-categories.update', $cat->id), [
            'name' => 'Water & Utilities',
            'is_active' => 0,
        ])->assertRedirect();

        $cat->refresh();
        $this->assertSame('Water & Utilities', $cat->name);
        $this->assertFalse((bool) $cat->is_active);

        // Deactivated categories do not appear on the create form.
        $this->actingAs($owner)->get(route('expenses.create'))
            ->assertOk()
            ->assertDontSee('Water & Utilities');
    }

    public function test_expenses_are_scoped_to_the_users_branch(): void
    {
        $this->seed(\Database\Seeders\AccountSeeder::class);
        $this->seed(\Database\Seeders\ExpenseCategorySeeder::class);

        $branchA = \App\Models\Branch::create(['name' => 'Branch A', 'address' => 'Addis', 'phone' => '0911']);
        $branchB = \App\Models\Branch::create(['name' => 'Branch B', 'address' => 'Addis', 'phone' => '0912']);
        $headOffice = User::factory()->create(['role' => 'owner_admin', 'branch_id' => null]);
        $managerA = User::factory()->create(['role' => 'owner_admin', 'branch_id' => $branchA->id]);
        $cat = ExpenseCategory::where('name', 'Rent')->firstOrFail();

        foreach ([$branchA, $branchB] as $branch) {
            $this->actingAs($headOffice)->post(route('expenses.store'), [
                'expense_date' => now()->toDateString(),
                'expense_category_id' => $cat->id,
                'branch_id' => $branch->id,
                'amount' => 100,
                'payment_method' => 'cash',
                'note' => 'rent of ' . $branch->name,
            ])->assertSessionHasNoErrors();
        }

        $html = $this->actingAs($managerA)->get(route('expenses.index'))->content();
        $this->assertStringContainsString('rent of Branch A', $html);
        $this->assertStringNotContainsString('rent of Branch B', $html);

        $foreign = Expense::where('branch_id', $branchB->id)->firstOrFail();
        $this->actingAs($managerA)->get(route('expenses.edit', $foreign->id))->assertForbidden();
        $this->actingAs($managerA)->put(route('expenses.update', $foreign->id), ['amount' => 1])->assertForbidden();
        $this->actingAs($managerA)->delete(route('expenses.destroy', $foreign->id))->assertForbidden();

        // The all-branch owner sees both.
        $both = $this->actingAs($headOffice)->get(route('expenses.index'))->content();
        $this->assertStringContainsString('rent of Branch B', $both);
    }

    public function test_repeating_expense_is_reminded_on_the_alerts_page(): void
    {
        $owner = $this->owner();
        $cat = $this->category('Electricity');

        // Recorded a month ago and set to repeat, so the next one is due today.
        $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => now()->subMonthNoOverflow()->toDateString(),
            'expense_category_id' => $cat->id,
            'amount' => 350,
            'payment_method' => 'cash',
            'repeat_monthly' => 1,
        ])->assertSessionHasNoErrors();

        $expense = Expense::firstOrFail();
        $this->assertTrue((bool) $expense->repeat_monthly);
        $this->assertSame(now()->toDateString(), $expense->next_due_date->toDateString());

        $this->actingAs($owner)->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('Monthly bills due now')
            ->assertSee('Electricity');
    }

    public function test_repeating_bill_reminder_shows_once_per_month_not_forever(): void
    {
        $owner = $this->owner();
        $cat = $this->category('Rent');

        // Recorded today with the monthly flag: the next one is a month away,
        // outside the reminder window, so nothing is due yet.
        $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $cat->id,
            'amount' => 4000,
            'payment_method' => 'cash',
            'repeat_monthly' => 1,
        ])->assertSessionHasNoErrors();

        $expense = Expense::firstOrFail();
        $this->assertStringNotContainsString('Monthly bills due now', $this->actingAs($owner)->get(route('alerts.index'))->content());

        // Same bill, but its stored due date has long passed (owner never
        // re-recorded it): the reminder must roll to this month's cycle and
        // show that date instead of the stale one, every month.
        $expense->update(['next_due_date' => now()->subMonthsNoOverflow(3)->toDateString()]);

        $html = $this->actingAs($owner)->get(route('alerts.index'))->content();
        $this->assertStringContainsString('Monthly bills due now', $html);
        $this->assertStringContainsString(now()->format('d M Y'), $html);
        $this->assertStringNotContainsString($expense->next_due_date->format('d M Y'), $html);
    }

    public function test_monthly_export_adds_an_expenses_sheet_and_summary_total(): void
    {
        $owner = $this->owner();
        $cat = $this->category('Water');

        $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $cat->id,
            'amount' => 200,
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $month = (int) now()->month;
        $year = (int) now()->year;
        $branchId = $owner->branch_id;

        $titles = array_map(
            fn ($sheet) => $sheet->title(),
            (new \App\Exports\MonthlyAccountingExport($branchId, $month, $year))->sheets()
        );
        $this->assertContains('Expenses', $titles);

        $rows = (new \App\Exports\Sheets\ExpensesSheetExport($branchId, $month, $year))->collection();
        $this->assertCount(1, $rows);

        $summary = collect((new \App\Exports\Sheets\SummarySheetExport($branchId, $month, $year))->array())
            ->first(fn ($row) => ($row[0] ?? null) === 'Total Expenses');
        $this->assertNotNull($summary, 'Summary must carry the total expenses line.');
        $this->assertEqualsWithDelta(200.0, (float) $summary[1], 0.001);
    }
}
