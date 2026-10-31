<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\PurchaseBatch;
use App\Models\StockCard;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A purchase entered by mistake can be removed while nothing has moved yet.
 *
 * The row, its stock card and its shelf stock go; the ledger keeps the
 * original entry and a mirror image, so the books still balance and the
 * deletion is explained rather than hidden.
 */
class PurchaseDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Supplier $supplier;
    private DrugCategory $category;
    private Drug $drug;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->supplier = Supplier::create(['name' => 'Medi Supply']);
        $this->category = DrugCategory::create(['name' => 'Antibiotics']);

        $this->owner = User::create([
            'name' => 'Owner', 'username' => 'owner', 'email' => 'owner@example.com',
            'password' => 'secret1234', 'role' => 'owner_admin',
            'branch_id' => $this->branch->id,
        ]);

        $this->drug = Drug::create([
            'name' => 'Doxycycline',
            'category_id' => $this->category->id,
            'unit_top' => 'box',
            'unit_middle' => 'strip',
            'unit_base' => 'tablet',
            'top_holds' => 4,
            'middle_holds' => 12,
            'minimum_stock_level' => 1,
        ]);
    }

    private function buy(int $boxes = 2): PurchaseBatch
    {
        $this->actingAs($this->owner)->post(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'items' => [[
                'item_type' => 'drug',
                'item_id' => $this->drug->id,
                'batch_number' => 'BOX-1',
                'quantity_received' => $boxes,
                'quantity_unit' => 'pack',
                'cost_per_unit' => 240,
                'selling_price_per_unit' => 288,
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ])->assertSessionHasNoErrors();

        return PurchaseBatch::where('drug_id', $this->drug->id)->firstOrFail();
    }

    public function test_an_untouched_purchase_is_deleted_and_its_entry_reversed(): void
    {
        $batch = $this->buy();

        $this->assertSame(96, (int) $batch->quantity_received);
        $this->assertTrue(StockCard::where('purchase_batch_id', $batch->id)->exists());

        $this->actingAs($this->owner)
            ->delete(route('purchases.destroy', $batch))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('purchase_batches', ['id' => $batch->id]);
        $this->assertDatabaseMissing('stock_cards', ['purchase_batch_id' => $batch->id]);

        // Both the purchase and its mirror image are on file, and the
        // inventory account nets to zero.
        $this->assertSame(1, JournalEntry::where('reference_type', 'purchase')
            ->where('reference_id', $batch->id)->count());

        $reversal = JournalEntry::where('reference_type', 'purchase_reversal')
            ->where('reference_id', $batch->id)
            ->firstOrFail();

        $this->assertEqualsWithDelta(480.0, (float) $reversal->total_debit, 0.001);
        $this->assertEqualsWithDelta(480.0, (float) $reversal->total_credit, 0.001);

        $inventory = Account::where('code', '1200')->firstOrFail();
        $net = Ledger::where('account_id', $inventory->id)->get()
            ->sum(fn (Ledger $line) => (float) $line->debit - (float) $line->credit);

        $this->assertEqualsWithDelta(0.0, $net, 0.001);
    }

    public function test_a_purchase_that_has_already_sold_stock_cannot_be_deleted(): void
    {
        $batch = $this->buy();

        $this->actingAs($this->owner)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [[
                'drug_id' => 'drug:' . $this->drug->id,
                'quantity' => 3,
                'unit' => 'strip',
            ]],
            'payments' => [['payment_method' => 'cash', 'amount' => 216]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->delete(route('purchases.destroy', $batch))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('purchase_batches', ['id' => $batch->id]);
        $this->assertSame(60, (int) $batch->fresh()->quantity_remaining);
    }

    public function test_only_the_owner_can_delete_a_purchase(): void
    {
        $batch = $this->buy();

        $manager = User::create([
            'name' => 'Manager', 'username' => 'manager', 'email' => 'manager@example.com',
            'password' => 'secret1234', 'role' => 'branch_manager',
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($manager)
            ->delete(route('purchases.destroy', $batch))
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_batches', ['id' => $batch->id]);
    }
}
