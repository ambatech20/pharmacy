<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Services\FinancialSummaryService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The financial report: the terms table, the two statements, and the
 * collapsed voucher list underneath them.
 *
 * The point of these is that the statements are derived, not typed in. Every
 * figure has to fall out of the ledger PMAS wrote, and because each journal
 * entry balances, the balance sheet has to balance too.
 */
class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    protected FinancialSummaryService $summary;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccountSeeder::class, ExpenseCategorySeeder::class]);
        $this->summary = new FinancialSummaryService();
        $this->branch = Branch::create(['name' => 'Main Branch']);
    }

    /**
     * Post one balanced journal entry.
     *
     * @param  array<string, array{0: float, 1: float}>  $lines  account code => [debit, credit]
     */
    protected function entry(string $date, array $lines, string $description = 'Test entry'): JournalEntry
    {
        $entry = JournalEntry::create([
            'voucher_number' => 'JE-' . str_pad((string) (JournalEntry::count() + 1), 4, '0', STR_PAD_LEFT),
            'date' => $date,
            'description' => $description,
            'reference_type' => 'manual',
            'branch_id' => $this->branch->id,
            'total_debit' => 0,
            'total_credit' => 0,
        ]);

        $debit = 0.0;
        $credit = 0.0;

        foreach ($lines as $code => [$debitAmount, $creditAmount]) {
            $account = Account::where('code', $code)->firstOrFail();

            Ledger::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $account->id,
                'branch_id' => $this->branch->id,
                'debit' => $debitAmount,
                'credit' => $creditAmount,
                'description' => $description,
            ]);

            $debit += $debitAmount;
            $credit += $creditAmount;
        }

        // A real entry always balances; assert it here so a typo in a test
        // fixture cannot quietly produce a plausible-looking report.
        $this->assertEqualsWithDelta($debit, $credit, 0.001, 'Fixture entry does not balance.');

        $entry->update(['total_debit' => $debit, 'total_credit' => $credit]);

        return $entry;
    }

    public function test_terms_are_in_pmas_words_not_accounting_jargon(): void
    {
        $terms = FinancialSummaryService::terms();
        $meanings = collect($terms)->pluck('meaning', 'line');

        // The example the owner gave: accounts payable means owed to suppliers.
        $this->assertStringContainsString('Owed to suppliers', $meanings['Accounts payable']);

        // Every term needs a line and an explanation.
        foreach ($terms as $term) {
            $this->assertNotSame('', trim($term['line']));
            $this->assertGreaterThan(20, strlen($term['meaning']), $term['line'] . ' needs a real explanation.');
        }
    }

    public function test_profit_and_loss_sums_the_period(): void
    {
        // Sold 1,000 of stock that cost 600.
        $this->entry(now()->startOfMonth()->toDateString(), [
            '1001' => [1000, 0],   // cash in
            '4000' => [0, 1000],   // sales revenue
            '5000' => [600, 0],    // cost of the stock handed over
            '1200' => [0, 600],    // that stock leaves the shelf
        ], 'Sale');

        // Rent paid.
        $this->entry(now()->startOfMonth()->toDateString(), [
            '6000' => [250, 0],
            '1001' => [0, 250],
        ], 'Rent');

        // Last month, must not be counted.
        $this->entry(now()->subMonthNoOverflow()->startOfMonth()->toDateString(), [
            '1001' => [900, 0],
            '4000' => [0, 900],
            '5000' => [100, 0],
            '1200' => [0, 100],
        ], 'Old sale');

        $pnl = $this->summary->profitAndLoss(null, [
            now()->startOfMonth()->startOfDay(),
            now()->endOfMonth()->endOfDay(),
        ]);

        $this->assertEqualsWithDelta(1000.00, $pnl['revenue'], 0.01);
        $this->assertEqualsWithDelta(600.00, $pnl['cogs'], 0.01);
        $this->assertEqualsWithDelta(400.00, $pnl['gross_profit'], 0.01, 'Revenue minus cost of what was sold.');
        $this->assertEqualsWithDelta(250.00, $pnl['operating_expenses'], 0.01);
        $this->assertEqualsWithDelta(150.00, $pnl['net_profit'], 0.01);
    }

    public function test_inventory_shrinkage_counts_as_an_operating_cost_not_cogs(): void
    {
        $this->entry(now()->toDateString(), [
            '5100' => [75, 0],
            '1200' => [0, 75],
        ], 'Expired stock written off');

        $pnl = $this->summary->profitAndLoss(null, null);

        $this->assertEqualsWithDelta(0.0, $pnl['cogs'], 0.01);
        $this->assertEqualsWithDelta(75.00, $pnl['operating_expenses'], 0.01);
        $this->assertEqualsWithDelta(-75.00, $pnl['net_profit'], 0.01);
    }

    public function test_balance_sheet_adds_up(): void
    {
        // Opening capital 5,000, then stock bought for 2,000 on credit.
        $this->entry(now()->subYear()->toDateString(), [
            '1001' => [5000, 0],
            '3000' => [0, 5000],
        ], 'Opening balance');

        $this->entry(now()->subMonthNoOverflow()->toDateString(), [
            '1200' => [2000, 0],  // stock in
            '2000' => [0, 2000],  // owed to supplier
        ], 'Stock on credit');

        $this->entry(now()->toDateString(), [
            '1001' => [500, 0],   // cash in
            '4000' => [0, 500],   // revenue
            '5000' => [300, 0],   // cost of that stock
            '1200' => [0, 300],   // and it leaves the shelf
        ], 'Sale');

        $sheet = $this->summary->balanceSheet(null);

        $this->assertEqualsWithDelta(5500.00, $sheet['cash'], 0.01);
        $this->assertEqualsWithDelta(1700.00, $sheet['inventory'], 0.01, 'Stock valued at cost, less what was sold.');
        $this->assertEqualsWithDelta(7200.00, $sheet['total_assets'], 0.01);
        $this->assertEqualsWithDelta(2000.00, $sheet['payables'], 0.01);
        $this->assertEqualsWithDelta(5000.00, $sheet['equity'], 0.01);
        $this->assertEqualsWithDelta(200.00, $sheet['retained_profit'], 0.01);

        // The whole point: assets must equal what they are financed by.
        $this->assertEqualsWithDelta(
            $sheet['total_assets'],
            $sheet['total_liabilities_and_equity'],
            0.01,
            'The balance sheet does not balance.'
        );
    }

    public function test_balance_sheet_is_a_snapshot_at_the_end_of_the_period(): void
    {
        $this->entry(now()->subYear()->toDateString(), [
            '1001' => [1000, 0],
            '3000' => [0, 1000],
        ], 'Opening balance');

        $this->entry(now()->subYear()->toDateString(), [
            '1200' => [500, 0],   // stock bought for cash
            '1001' => [0, 500],
        ], 'Stock');

        $this->entry(now()->toDateString(), [
            '1001' => [250, 0],
            '4000' => [0, 250],
            '5000' => [100, 0],
            '1200' => [0, 100],
        ], 'Sale this month');

        // Looking at last year: the opening cash and the stock are there, but
        // this month's sale is not.
        $sheet = $this->summary->balanceSheet(null, now()->subYear()->endOfYear());

        $this->assertEqualsWithDelta(500.00, $sheet['cash'], 0.01);
        $this->assertEqualsWithDelta(500.00, $sheet['inventory'], 0.01);
        $this->assertEqualsWithDelta(0.0, $sheet['retained_profit'], 0.01, 'No profit yet at that date.');

        // And it balances at that point in time too.
        $this->assertEqualsWithDelta(
            $sheet['total_assets'],
            $sheet['total_liabilities_and_equity'],
            0.01
        );
    }

    public function test_the_page_shows_the_terms_the_statements_and_a_collapsed_voucher_list(): void
    {
        $this->entry(now()->toDateString(), [
            '1001' => [750, 0],
            '4000' => [0, 750],
            '5000' => [400, 0],
            '1200' => [0, 400],
        ], 'Sale');

        $admin = \App\Models\User::create([
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => 'secret1234',
            'role' => 'owner_admin',
        ]);

        $response = $this->actingAs($admin)->get(route('reports.financial'));

        $response->assertOk();

        // The terms table, in the owner's words.
        $response->assertSee('What these terms mean');
        $response->assertSee('Owed to suppliers');

        // The two statements, with figures off the ledger.
        $response->assertSee('Profit and loss');
        $response->assertSee('Balance sheet');
        $response->assertSee('Gross profit');
        $response->assertSee('Net profit');
        $response->assertSee('400.00');   // cost of goods sold
        $response->assertSee('350.00');   // net profit: 750 - 400

        // The voucher list is collapsed by default, and its summary bar
        // carries the period totals rather than just the page.
        $response->assertSee('Debit and credit list');
        $response->assertSee('1 voucher');

        $content = $response->getContent();

        // <details> without the `open` attribute - closed until tapped.
        $this->assertMatchesRegularExpression(
            '/<details class="group[^"]*"[^>]*>\s*<summary[^>]*>\s*<span>\s*<span[^>]*>Debit and credit list/',
            $content,
            'The voucher list should start collapsed.'
        );

        // The glossary belongs underneath the figures it explains, not above
        // them: the page should open on profit, not on vocabulary.
        $this->assertGreaterThan(
            strpos($content, 'Debit and credit list'),
            strpos($content, 'What these terms mean'),
            'The terms table should come after the statements and the voucher list.'
        );
    }

    public function test_the_voucher_list_only_counts_the_filtered_period(): void
    {
        $this->entry(now()->subYear()->toDateString(), [
            '1001' => [900, 0],
            '4000' => [0, 900],
            '5000' => [300, 0],
            '1200' => [0, 300],
        ], 'Old sale');

        $this->entry(now()->toDateString(), [
            '1001' => [100, 0],
            '4000' => [0, 100],
            '5000' => [40, 0],
            '1200' => [0, 40],
        ], 'This month');

        $admin = \App\Models\User::create([
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => 'secret1234',
            'role' => 'owner_admin',
        ]);

        $this->actingAs($admin)->get(route('reports.financial', [
            'month' => now()->month,
            'year' => now()->year,
        ]))
            ->assertOk()
            ->assertSee('1 voucher')
            ->assertSee('This month')
            ->assertDontSee('Old sale')
            // The statements follow the same filter: this month's net profit is
            // 100 - 40 = 60, not last month's figures folded in.
            ->assertSee('60.00')
            ->assertDontSee('900.00');
    }
}
