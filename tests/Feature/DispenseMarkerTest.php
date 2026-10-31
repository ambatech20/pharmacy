<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\IssuedItem;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dispensing marker on the sale screen ("#1 - 40 left") and the FEFO/FIFO
 * order it promises.
 *
 * The marker is recomputed from the batches on every request (nothing is
 * stored), and its ORDER BY is deliberately identical to
 * InventoryService::selectBatchesForDispensing(), so the batch the seller is
 * told about is always the batch that is actually consumed.
 *
 * The marker is served by the /search-drug endpoint rather than rendered into
 * the sale screen. The screen used to embed the whole catalogue so the browser
 * could filter it, which cost ~450 KB and over a second at 800 drugs; the
 * marker moved to the search results with it. These tests therefore assert
 * against the endpoint that now produces it.
 */
class DispenseMarkerTest extends TestCase
{
    use RefreshDatabase;

    protected User $clerk;
    protected Branch $branch;
    protected Supplier $supplier;
    protected Drug $drug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->supplier = Supplier::create(['name' => 'Medi Supply']);
        $this->drug = Drug::create([
            'name' => 'Paracetamol',
            'strength' => '500mg',
            'category_id' => DrugCategory::create(['name' => 'Pain relief'])->id,
            'unit_top' => 'pack',
            'top_holds' => 10,
            'unit_base' => 'tablet',
        ]);
        $this->clerk = User::create([
            'name' => 'Clerk',
            'username' => 'clerk',
            'email' => 'clerk@example.com',
            'password' => 'secret1234',
            'role' => 'pharmacist_clerk',
            'branch_id' => $this->branch->id,
        ]);
    }

    protected function batch(string $batchNumber, string $expiry, int $remaining): PurchaseBatch
    {
        return PurchaseBatch::create([
            'drug_id' => $this->drug->id,
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'batch_number' => $batchNumber,
            'quantity_received' => $remaining,
            'quantity_remaining' => $remaining,
            'cost_per_unit' => 10,
            'selling_price_per_unit' => 15,
            'expiry_date' => $expiry,
            'purchase_date' => now()->toDateString(),
        ]);
    }

    public function test_marker_shows_the_earliest_expiring_batch_and_its_quantity(): void
    {
        $this->batch('SOON', now()->addDays(30)->toDateString(), 40);
        $this->batch('LATER', now()->addDays(300)->toDateString(), 100);

        $response = $this->actingAs($this->clerk)->getJson(route('search.drug', ['q' => 'Parac']));

        $response->assertOk();
        $this->assertSame(['#1 - 40 left'], array_column($response->json('results'), 'marker'));
        $this->assertStringNotContainsString('100 left', $response->getContent());
    }

    /**
     * The original problem: three batches bought the same day with the same
     * expiry date. The oldest row must be consumed first, and the marker must
     * say so.
     */
    public function test_same_day_same_expiry_batches_dispense_oldest_first(): void
    {
        $expiry = now()->addYear()->toDateString();
        $first = $this->batch('ONE', $expiry, 10);
        $second = $this->batch('TWO', $expiry, 20);
        $third = $this->batch('THREE', $expiry, 30);

        $markers = $this->actingAs($this->clerk)->getJson(route('search.drug', ['q' => 'Parac']))
            ->assertOk()
            ->json('results');
        $this->assertSame(['#1 - 10 left'], array_column($markers, 'marker'));

        $this->actingAs($this->clerk)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [[
                'drug_id' => 'drug:' . $this->drug->id,
                'quantity' => 3,
                'unit' => 'tablet',
                'selling_price' => 15,
            ]],
            'payments' => [['payment_method' => 'cash', 'amount' => 45]],
        ])->assertRedirect();

        $issued = IssuedItem::first();
        $this->assertSame($first->id, $issued->purchase_batch_id);
        $this->assertSame(7, (int) $first->fresh()->quantity_remaining);
        $this->assertSame(20, (int) $second->fresh()->quantity_remaining);
        $this->assertSame(30, (int) $third->fresh()->quantity_remaining);
    }

    public function test_marker_is_omitted_when_the_item_has_no_usable_stock(): void
    {
        $this->batch('GONE', now()->addYear()->toDateString(), 0);

        // No marker at all, rather than a misleading "#1 - 0 left".
        $results = $this->actingAs($this->clerk)->getJson(route('search.drug', ['q' => 'Parac']))
            ->assertOk()
            ->json('results');
        $this->assertCount(1, $results);
        $this->assertNull($results[0]['marker']);    }

    public function test_marker_ignores_expired_batches(): void
    {
        $this->batch('DEAD', now()->subDay()->toDateString(), 999);
        $this->batch('LIVE', now()->addYear()->toDateString(), 7);

        $markers = $this->actingAs($this->clerk)->getJson(route('search.drug', ['q' => 'Parac']))
            ->assertOk()
            ->json('results');
        $this->assertSame(['#1 - 7 left'], array_column($markers, 'marker'));
        $this->assertStringNotContainsString('999 left', json_encode($markers));
    }

    public function test_marker_recomputes_when_the_first_batch_is_used_up(): void
    {
        $expiry = now()->addYear()->toDateString();
        $first = $this->batch('ONE', $expiry, 2);
        $this->batch('TWO', $expiry, 50);

        $this->assertSame(['#1 - 2 left'], array_column(
            $this->actingAs($this->clerk)->getJson(route('search.drug', ['q' => 'Parac']))->json('results'),
            'marker'
        ));

        // Sell the first batch dry, then reload: the marker must move to #1 on
        // what is now the next batch out, with no stored state to renumber.
        $first->update(['quantity_remaining' => 0]);

        $this->assertSame(['#1 - 50 left'], array_column(
            $this->actingAs($this->clerk)->getJson(route('search.drug', ['q' => 'Parac']))->json('results'),
            'marker'
        ));
    }
}
