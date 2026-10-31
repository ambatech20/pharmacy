<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $accounting;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accounting = new AccountingService();
        $this->branch = Branch::create(['name' => 'Main Branch']);

        // Same chart of accounts the migration/seeder ship — mirrors
        // production so hardcoded account_codes in the service resolve.
        $this->seed(\Database\Seeders\AccountSeeder::class);
    }

    /** postEntry() must reject any set of lines where debits and credits don't balance. */
    public function test_unbalanced_entry_is_rejected(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Debits and credits must balance.');

        $this->accounting->postEntry('Bad entry', $this->branch->id, 'test', 1, [
            ['account_code' => '1001', 'debit' => 100, 'credit' => 0],
            ['account_code' => '4000', 'debit' => 0, 'credit' => 90],
        ]);
    }

    /** A balanced entry should post one journal entry and one ledger line per account. */
    public function test_balanced_entry_posts_successfully(): void
    {
        $entry = $this->accounting->postEntry('Test sale', $this->branch->id, 'test', 1, [
            ['account_code' => '1001', 'debit' => 100, 'credit' => 0],
            ['account_code' => '4000', 'debit' => 0, 'credit' => 100],
        ]);

        $this->assertEquals(100, $entry->total_debit);
        $this->assertEquals(100, $entry->total_credit);
        $this->assertDatabaseCount('ledgers', 2);
    }

    /** recordSale() for a cash payment debits Cash and credits Revenue + COGS/Inventory. */
    public function test_record_sale_posts_to_correct_accounts_for_cash(): void
    {
        $this->accounting->recordSale(100.00, 60.00, $this->branch->id, 1, [
            ['payment_method' => 'cash', 'amount' => 100.00],
        ]);

        $cash = Account::where('code', '1001')->first();
        $revenue = Account::where('code', '4000')->first();
        $cogs = Account::where('code', '5000')->first();
        $inventory = Account::where('code', '1200')->first();

        $this->assertEquals(100.00, $cash->ledgers()->sum('debit'));
        $this->assertEquals(100.00, $revenue->ledgers()->sum('credit'));
        $this->assertEquals(60.00, $cogs->ledgers()->sum('debit'));
        $this->assertEquals(60.00, $inventory->ledgers()->sum('credit'));
    }

    /** A split payment (cash + Telebirr) must debit each method's own account, still balanced overall. */
    public function test_record_sale_splits_correctly_across_payment_methods(): void
    {
        $this->accounting->recordSale(100.00, 60.00, $this->branch->id, 1, [
            ['payment_method' => 'cash', 'amount' => 40.00],
            ['payment_method' => 'telebirr', 'amount' => 60.00],
        ]);

        $cash = Account::where('code', '1001')->first();
        $telebirr = Account::where('code', '1010')->first();

        $this->assertEquals(40.00, $cash->ledgers()->sum('debit'));
        $this->assertEquals(60.00, $telebirr->ledgers()->sum('debit'));
    }

    /** An unknown payment method must fail loudly rather than silently post to the wrong account. */
    public function test_unknown_payment_method_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->accounting->recordSale(100.00, 60.00, $this->branch->id, 1, [
            ['payment_method' => 'crypto', 'amount' => 100.00],
        ]);
    }
}
