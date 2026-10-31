<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Products use the same three-rung packing as drugs: a box of 12 bottles is
 * bought as one box, stored as 12 bottles, and sold either as a box or a
 * bottle. The minimum is entered in boxes, because that is how the shelf is
 * counted.
 */
class ProductPackingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Supplier $supplier;
    private Product $product;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->supplier = Supplier::create(['name' => 'Medi Supply']);
        $this->owner = User::create([
            'name' => 'Owner', 'username' => 'owner', 'email' => 'owner@example.com',
            'password' => 'secret1234', 'role' => 'owner_admin',
            'branch_id' => $this->branch->id,
        ]);

        $this->product = Product::create([
            'name' => 'Shampoo 400 ml',
            'sku' => 'HC-SHP-400',
            'category_id' => ProductCategory::create(['name' => 'Hair Care'])->id,
            'purchase_price' => 80,
            'selling_price' => 120,
            'minimum_stock' => 2,
            'unit_top' => 'box',
            'unit_middle' => null,
            'unit_base' => 'bottle',
            'top_holds' => 12,
            'middle_holds' => null,
            'sell_top' => true,
            'sell_middle' => false,
            'sell_base' => true,
        ]);
    }

    private function buyTwoBoxes(): PurchaseBatch
    {
        $this->actingAs($this->owner)->post(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'items' => [[
                'item_type' => 'product',
                'item_id' => $this->product->id,
                'batch_number' => 'SH-1',
                'quantity_received' => 2,
                'quantity_unit' => 'top',
                'cost_per_unit' => 1200,
                'selling_price_per_unit' => 1440,
                'expiry_date' => now()->addYears(3)->toDateString(),
            ]],
        ])->assertSessionHasNoErrors();

        return PurchaseBatch::where('product_id', $this->product->id)->firstOrFail();
    }

    public function test_a_product_box_is_bought_as_a_box_and_stored_as_bottles(): void
    {
        $batch = $this->buyTwoBoxes();

        $this->assertSame(24, (int) $batch->quantity_received);
        $this->assertEqualsWithDelta(100.0, (float) $batch->cost_per_unit, 0.001);
        $this->assertEqualsWithDelta(120.0, (float) $batch->selling_price_per_unit, 0.001);
        $this->assertSame('2 boxes', $this->product->fresh()->describeQuantity(24));
    }

    public function test_the_till_offers_only_the_product_units_that_are_ticked(): void
    {
        $this->buyTwoBoxes();

        $results = $this->actingAs($this->owner)
            ->getJson(route('search.drug', ['q' => 'Shampoo']))
            ->assertOk()
            ->json('results');

        $entry = collect($results)->firstWhere('key', 'product:' . $this->product->id);

        $this->assertNotNull($entry);
        $this->assertSame([
            ['key' => 'top', 'name' => 'box', 'factor' => 12],
            ['key' => 'base', 'name' => 'bottle', 'factor' => 1],
        ], $entry['levels']);
    }

    public function test_selling_a_product_box_deducts_twelve_bottles(): void
    {
        $batch = $this->buyTwoBoxes();

        $this->actingAs($this->owner)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [[
                'drug_id' => 'product:' . $this->product->id,
                'quantity' => 1,
                'unit' => 'top',
                'selling_price' => 1440,
            ]],
            'payments' => [['payment_method' => 'cash', 'amount' => 1440]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(12, (int) $batch->fresh()->quantity_remaining);
        $this->assertDatabaseHas('orders', ['total_amount' => 1440]);
    }

    public function test_an_unticked_product_unit_cannot_be_sold(): void
    {
        $this->buyTwoBoxes();

        $this->actingAs($this->owner)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [[
                'drug_id' => 'product:' . $this->product->id,
                'quantity' => 1,
                'unit' => 'middle',
                'selling_price' => 1440,
            ]],
            'payments' => [['payment_method' => 'cash', 'amount' => 1440]],
        ])->assertSessionHasErrors('error');
    }

    public function test_the_minimum_is_counted_in_boxes_not_bottles(): void
    {
        $this->buyTwoBoxes();

        // Sell one box: 12 bottles left against a minimum of 2 boxes = 24.
        $this->actingAs($this->owner)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [[
                'drug_id' => 'product:' . $this->product->id,
                'quantity' => 1,
                'unit' => 'top',
                'selling_price' => 1440,
            ]],
            'payments' => [['payment_method' => 'cash', 'amount' => 1440]],
        ])->assertSessionHasNoErrors();

        $fresh = $this->product->fresh();
        $this->assertSame(12, $fresh->current_stock);
        $this->assertTrue($fresh->isBelowMinimum($fresh->current_stock));

        $this->actingAs($this->owner)->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('Shampoo 400 ml')
            ->assertSee('minimum 2 boxes');
    }
}
