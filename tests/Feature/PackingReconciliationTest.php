<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\IssuedItem;
use App\Models\JournalEntry;
use App\Models\PurchaseBatch;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The books and the shelf agree, on a packing where nothing is ten.
 *
 * Every rung the sale, purchase, write-off and return screens offer is turned
 * back into base units before it touches stock or money. These tests follow one
 * box of 4 strips of 12 the whole way and check the numbers that end up on the
 * shelf and in the ledger.
 */
class PackingReconciliationTest extends TestCase
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

        // 4 strips x 12 tablets = 48 tablets in a box.
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

    /** Buy $boxes boxes at 240 each; 288 is the shelf price for a whole box. */
    private function buy(int $boxes = 2): PurchaseBatch
    {
        $this->actingAs($this->owner)->post(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            // Deliberately no branch_id: the purchase form does not ask for
            // one. The branch comes from the signed-in user.
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

    private function makeDrug(string $name, int $minimum): Drug
    {
        return Drug::create([
            'name' => $name,
            'category_id' => $this->category->id,
            'unit_top' => 'box',
            'unit_middle' => 'strip',
            'unit_base' => 'tablet',
            'top_holds' => 4,
            'middle_holds' => 12,
            'minimum_stock_level' => $minimum,
        ]);
    }

    private function addBatch(Drug $drug, int $tablets, string $expiry): void
    {
        PurchaseBatch::create([
            'drug_id' => $drug->id,
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'B-' . uniqid(),
            'quantity_received' => $tablets,
            'quantity_remaining' => $tablets,
            'cost_per_unit' => 5,
            'selling_price_per_unit' => 6,
            'expiry_date' => $expiry,
            'purchase_date' => now(),
        ]);
    }

    /**
     * The form has no branch field, so the journal entry must take the branch
     * the rest of the purchase was stamped with. Before this test, the entry
     * was written with a null branch and the whole purchase rolled back.
     */
    public function test_a_purchase_from_the_real_form_posts_to_the_users_branch(): void
    {
        $batch = $this->buy();

        $this->assertSame(96, (int) $batch->quantity_received);
        $this->assertEqualsWithDelta(5.0, (float) $batch->cost_per_unit, 0.001);

        $entry = JournalEntry::where('reference_type', 'purchase')
            ->where('reference_id', $batch->id)
            ->firstOrFail();

        $this->assertSame($this->branch->id, (int) $entry->branch_id);
        $this->assertEqualsWithDelta(480.0, (float) $entry->total_debit, 0.001);
        $this->assertEqualsWithDelta(480.0, (float) $entry->total_credit, 0.001);
    }

    public function test_buying_selling_writing_off_and_returning_a_box_adds_up(): void
    {
        $batch = $this->buy();

        // Sell 3 strips = 36 tablets at 6.00 each.
        $this->actingAs($this->owner)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [[
                'drug_id' => 'drug:' . $this->drug->id,
                'quantity' => 3,
                'unit' => 'strip',
            ]],
            'payments' => [['payment_method' => 'cash', 'amount' => 216]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(60, (int) $batch->fresh()->quantity_remaining);

        // Write off one strip: 12 tablets at 5.00 cost = 60.00.
        $this->actingAs($this->owner)->post(route('adjustments.store'), [
            'purchase_batch_id' => $batch->id,
            'quantity' => 1,
            'quantity_unit' => 'strip',
            'reason' => 'damaged',
        ])->assertSessionHasNoErrors();

        $this->assertSame(48, (int) $batch->fresh()->quantity_remaining);
        $this->assertEqualsWithDelta(
            60.0,
            (float) StockAdjustment::query()->latest('id')->value('total_amount'),
            0.001
        );

        // The customer brings one strip back: 12 tablets at 6.00 = 72.00.
        $issued = IssuedItem::where('purchase_batch_id', $batch->id)->firstOrFail();

        $this->actingAs($this->owner)->post(route('returns.customer.store'), [
            'issued_item_id' => $issued->id,
            'quantity' => 1,
            'quantity_unit' => 'strip',
            'refund_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->assertSame(60, (int) $batch->fresh()->quantity_remaining);

        // 60 tablets left at 5.00 = 300 on the shelf, read back as 1 box + 1 strip.
        $this->assertSame('1 box 1 strip', $this->drug->describeQuantity(60));
        $this->assertEqualsWithDelta(300.0, 60 * (float) $batch->fresh()->cost_per_unit, 0.001);
    }

    /**
     * A bottle has one rung. The write-off screen must offer "bottle" rather
     * than the old tablet/strip/pack, and posting it must take two bottles off
     * the shelf - not two tablets.
     */
    public function test_a_one_rung_bottle_is_written_off_as_a_bottle(): void
    {
        $syrup = Drug::create([
            'name' => 'Paracetamol Syrup',
            'category_id' => $this->category->id,
            'unit_top' => 'bottle',
            'unit_base' => 'bottle',
            'top_holds' => 1,
            'minimum_stock_level' => 1,
        ]);

        $batch = PurchaseBatch::create([
            'drug_id' => $syrup->id,
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'SYR-1',
            'quantity_received' => 5,
            'quantity_remaining' => 5,
            'cost_per_unit' => 40,
            'selling_price_per_unit' => 55,
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_date' => now(),
        ]);

        $this->assertSame(['bottle'], array_column($syrup->packingLevels(), 'name'));

        $this->actingAs($this->owner)->post(route('adjustments.store'), [
            'purchase_batch_id' => $batch->id,
            'quantity' => 2,
            'quantity_unit' => 'bottle',
            'reason' => 'damaged',
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, (int) $batch->fresh()->quantity_remaining);
    }

    /** A unit the item is not packed in is refused instead of silently counted as one. */
    public function test_a_write_off_rejects_a_unit_the_item_does_not_have(): void
    {
        $batch = $this->buy();

        $this->actingAs($this->owner)->post(route('adjustments.store'), [
            'purchase_batch_id' => $batch->id,
            'quantity' => 1,
            'quantity_unit' => 'crate',
            'reason' => 'damaged',
        ])->assertSessionHasErrors('quantity_unit');

        $this->assertSame(96, (int) $batch->fresh()->quantity_remaining);
    }

    /**
     * The dashboard card links straight to the alerts list, so the number and
     * the list have to be the same answer. Low stock is per drug: two batches
     * of 60 are 120 on the shelf, even though each batch alone is under a
     * 96-tablet minimum.
     */
    public function test_the_low_stock_card_counts_drugs_not_batches(): void
    {
        // Two batches, 60 each: 120 on the shelf against a 96-tablet minimum.
        // Counting batches would call this one low twice over.
        $stocked = $this->makeDrug('Well Stocked', 2);
        $this->addBatch($stocked, 60, now()->addMonths(6)->toDateString());
        $this->addBatch($stocked, 60, now()->addMonths(12)->toDateString());

        $short = $this->makeDrug('Nearly Out', 2);
        $this->addBatch($short, 60, now()->addMonths(9)->toDateString());

        $dashboard = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();
        $alerts = $this->actingAs($this->owner)->get(route('alerts.index'))->assertOk();

        $lowStock = $alerts->viewData('lowStockDrugs');

        // The card links to this list, so it must be the same answer.
        $this->assertSame($lowStock->count(), $dashboard->viewData('lowStockCount'));

        $this->assertNotContains($stocked->id, $lowStock->pluck('id')->all());
        $this->assertContains($short->id, $lowStock->pluck('id')->all());
    }

    /**
     * Expired stock is still on the shelf, but it cannot be sold, so it must
     * not make a drug look healthy. The dashboard card and the alerts list
     * apply the same rule, otherwise the link lands on a different answer.
     */
    public function test_expired_stock_does_not_clear_a_low_stock_warning(): void
    {
        // 96 tablets minimum: 48 expired + 48 sellable is still short.
        $drug = $this->makeDrug('Expired Leftovers', 2);
        $this->addBatch($drug, 48, now()->subMonths(2)->toDateString());
        $this->addBatch($drug, 48, now()->addMonths(6)->toDateString());

        $dashboard = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();
        $alerts = $this->actingAs($this->owner)->get(route('alerts.index'))->assertOk();

        $lowStock = $alerts->viewData('lowStockDrugs');

        $this->assertSame($lowStock->count(), $dashboard->viewData('lowStockCount'));
        $this->assertContains($drug->id, $lowStock->pluck('id')->all());
        $this->assertSame(48, (int) $drug->fresh()->current_stock);
    }
}
