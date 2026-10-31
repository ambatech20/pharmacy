<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\IssuedItem;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventory;
    protected Branch $branch;
    protected Drug $drug;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventory = new InventoryService();
        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->supplier = Supplier::create(['name' => 'Test Supplier']);
        $category = DrugCategory::create(['name' => 'Analgesics']);
        $this->drug = Drug::create([
            'name' => 'Paracetamol',
            'category_id' => $category->id,
            'unit_of_measure' => 'tablet',
            'units_per_strip' => 10,
            'strips_per_pack' => 10,
        ]);
    }

    protected function makeBatch(array $overrides = []): PurchaseBatch
    {
        return PurchaseBatch::create(array_merge([
            'drug_id' => $this->drug->id,
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'B-' . uniqid(),
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'cost_per_unit' => 1.00,
            'selling_price_per_unit' => 2.00,
            'expiry_date' => now()->addYear(),
            'purchase_date' => now(),
        ], $overrides));
    }

    /** Allocation should prefer the batch expiring soonest (FEFO). */
    public function test_allocation_picks_earliest_expiring_batch_first(): void
    {
        $farBatch = $this->makeBatch(['expiry_date' => now()->addYear(), 'quantity_remaining' => 100]);
        $nearBatch = $this->makeBatch(['expiry_date' => now()->addMonths(2), 'quantity_remaining' => 100]);

        $allocations = $this->inventory->selectBatchesForDispensing('drug', $this->drug->id, $this->branch->id, 30);

        $this->assertCount(1, $allocations);
        $this->assertTrue($allocations[0]['batch']->is($nearBatch));
        $this->assertEquals(30, $allocations[0]['qty']);
    }

    /** A single request can span multiple batches when one alone isn't enough. */
    public function test_allocation_spans_multiple_batches_when_needed(): void
    {
        $nearBatch = $this->makeBatch(['expiry_date' => now()->addMonths(1), 'quantity_remaining' => 20]);
        $farBatch = $this->makeBatch(['expiry_date' => now()->addMonths(6), 'quantity_remaining' => 50]);

        $allocations = $this->inventory->selectBatchesForDispensing('drug', $this->drug->id, $this->branch->id, 35);

        $this->assertCount(2, $allocations);
        $this->assertTrue($allocations[0]['batch']->is($nearBatch));
        $this->assertEquals(20, $allocations[0]['qty']);
        $this->assertTrue($allocations[1]['batch']->is($farBatch));
        $this->assertEquals(15, $allocations[1]['qty']);
    }

    /** Expired batches must never be offered for dispensing, even if they have stock. */
    public function test_expired_batches_are_excluded_from_allocation(): void
    {
        $this->makeBatch(['expiry_date' => now()->subDay(), 'quantity_remaining' => 100]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock available.');

        $this->inventory->selectBatchesForDispensing('drug', $this->drug->id, $this->branch->id, 10);
    }

    /** Requesting more than total remaining stock must fail, not partially fill. */
    public function test_insufficient_stock_throws_and_allocates_nothing(): void
    {
        $this->makeBatch(['quantity_remaining' => 5]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock available.');

        $this->inventory->selectBatchesForDispensing('drug', $this->drug->id, $this->branch->id, 10);
    }

    /** recordIssuance must decrement stock and total the line amounts. */
    public function test_record_issuance_decrements_batch_quantity_exactly_once(): void
    {
        $batch = $this->makeBatch(['quantity_remaining' => 50, 'selling_price_per_unit' => 2.00]);
        $allocations = [['batch' => $batch, 'qty' => 20]];

        $total = $this->inventory->recordIssuance($allocations, null, $this->branch->id, 2.00, null);

        $batch->refresh();
        $this->assertEquals(30, $batch->quantity_remaining);
        $this->assertEquals(40.00, $total);
    }

    /** Two sequential allocations against the same limited batch must not oversell. */
    public function test_sequential_allocations_do_not_oversell_a_shared_batch(): void
    {
        $batch = $this->makeBatch(['quantity_remaining' => 10]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($batch) {
            $allocations = $this->inventory->selectBatchesForDispensing('drug', $this->drug->id, $this->branch->id, 7);
            $this->inventory->recordIssuance($allocations, null, $this->branch->id, 2.00, null);
        });

        $batch->refresh();
        $this->assertEquals(3, $batch->quantity_remaining);

        $this->expectException(\Exception::class);
        $this->inventory->selectBatchesForDispensing('drug', $this->drug->id, $this->branch->id, 5);
    }

    /** Write-offs must refuse to remove more than what's actually left on the batch. */
    public function test_write_off_cannot_exceed_remaining_quantity(): void
    {
        $batch = $this->makeBatch(['quantity_remaining' => 5]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot write off more than the remaining batch quantity (5).');

        $this->inventory->writeOff($batch->id, 10, 'damaged', null, 1);
    }

    /** A customer return must be capped at the quantity sold on the original line. */
    public function test_customer_return_cannot_exceed_quantity_sold(): void
    {
        $batch = $this->makeBatch(['quantity_remaining' => 90]);
        $issued = IssuedItem::create([
            'purchase_batch_id' => $batch->id,
            'branch_id' => $this->branch->id,
            'quantity_issued' => 10,
            'selling_price_per_unit' => 2.00,
            'total_amount' => 20.00,
            'issue_date' => now(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot return more than the remaining sold quantity (10).');

        $this->inventory->restockCustomerReturn($issued, 15, 'cash', null, 1);
    }
}
