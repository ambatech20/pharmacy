<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);
        $this->branch = Branch::create(['name' => 'Test Branch']);
        $this->supplier = Supplier::create(['name' => 'Test Supplier']);
        $this->user = User::create([
            'name' => 'Test Clerk', 'email' => 'clerk@example.com',
            'password' => 'password', 'role' => User::ROLE_PHARMACIST_CLERK,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_mixed_purchase_creates_stock_and_accounting_records(): void
    {
        $drug = Drug::create(['name' => 'Paracetamol', 'category_id' => DrugCategory::create(['name' => 'Pain relief'])->id, 'unit_top' => 'pack', 'top_holds' => 10, 'unit_base' => 'tablet']);
        $product = Product::create([
            'name' => 'Face Wash', 'sku' => 'FW-001',
            'category_id' => ProductCategory::create(['name' => 'Skin Care'])->id,
            'purchase_price' => 40, 'selling_price' => 60, 'minimum_stock_level' => 5,
        ]);

        $response = $this->actingAs($this->user)->post(route('purchases.store'), [
            'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id,
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['item_type' => 'drug', 'item_id' => $drug->id, 'batch_number' => 'D-1', 'quantity_received' => 50, 'quantity_unit' => 'tablet', 'cost_per_unit' => 10, 'selling_price_per_unit' => 15, 'expiry_date' => now()->addYear()->toDateString()],
                ['item_type' => 'product', 'item_id' => $product->id, 'batch_number' => 'P-1', 'quantity_received' => 20, 'quantity_unit' => 'piece', 'cost_per_unit' => 40, 'selling_price_per_unit' => 60, 'expiry_date' => now()->addYears(5)->toDateString()],
            ],
        ]);

        $response->assertRedirect(route('purchases.index'));
        $this->assertDatabaseHas('purchase_batches', ['drug_id' => $drug->id, 'quantity_remaining' => 50]);
        $this->assertDatabaseHas('purchase_batches', ['product_id' => $product->id, 'quantity_remaining' => 20]);
        $this->assertDatabaseCount('stock_cards', 2);
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_mixed_sale_deducts_drug_and_product_stock_in_one_order(): void
    {
        $drug = Drug::create(['name' => 'Paracetamol', 'category_id' => DrugCategory::create(['name' => 'Pain relief'])->id, 'unit_top' => 'pack', 'top_holds' => 10, 'unit_base' => 'tablet']);
        $product = Product::create(['name' => 'Face Wash', 'sku' => 'FW-002', 'category_id' => ProductCategory::create(['name' => 'Skin Care'])->id, 'purchase_price' => 40, 'selling_price' => 60, 'minimum_stock_level' => 5]);
        PurchaseBatch::create(['drug_id' => $drug->id, 'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id, 'batch_number' => 'D-2', 'quantity_received' => 10, 'quantity_remaining' => 10, 'cost_per_unit' => 10, 'selling_price_per_unit' => 15, 'expiry_date' => now()->addYear(), 'purchase_date' => now()]);
        PurchaseBatch::create(['product_id' => $product->id, 'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id, 'batch_number' => 'P-2', 'quantity_received' => 10, 'quantity_remaining' => 10, 'cost_per_unit' => 40, 'selling_price_per_unit' => 60, 'purchase_date' => now()]);

        $response = $this->actingAs($this->user)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [
                ['drug_id' => 'drug:' . $drug->id, 'quantity' => 2, 'unit' => 'tablet', 'selling_price' => 15],
                ['drug_id' => 'product:' . $product->id, 'quantity' => 1, 'unit' => 'piece', 'selling_price' => 60],
            ],
            'payments' => [['payment_method' => 'cash', 'amount' => 90]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchase_batches', ['drug_id' => $drug->id, 'quantity_remaining' => 8]);
        $this->assertDatabaseHas('purchase_batches', ['product_id' => $product->id, 'quantity_remaining' => 9]);
        $this->assertDatabaseHas('orders', ['total_amount' => 90]);
        $this->assertSame(2, Order::first()->items()->count());
    }

    public function test_alerts_include_items_at_or_below_minimum_stock(): void
    {
        $product = Product::create([
            'name' => 'Toothbrush', 'sku' => 'TB-001',
            'category_id' => ProductCategory::create(['name' => 'Oral Care'])->id,
            'purchase_price' => 20, 'selling_price' => 35, 'minimum_stock' => 10,
        ]);
        PurchaseBatch::create([
            'product_id' => $product->id, 'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id, 'batch_number' => 'TB-1',
            'quantity_received' => 5, 'quantity_remaining' => 5,
            'cost_per_unit' => 20, 'selling_price_per_unit' => 35,
            'purchase_date' => now(),
        ]);

        $this->actingAs($this->user)->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('Toothbrush')
            ->assertSee('minimum 10');
    }

    /**
     * An expired batch cannot be sold, so it must not hide a product that is
     * below its minimum on sellable stock alone.
     */
    public function test_expired_product_stock_does_not_hide_a_low_stock_alert(): void
    {
        $product = Product::create([
            'name' => 'Expired Toothpaste', 'sku' => 'TP-EXP',
            'category_id' => ProductCategory::create(['name' => 'Oral Care'])->id,
            'purchase_price' => 20, 'selling_price' => 35, 'minimum_stock' => 10,
        ]);

        PurchaseBatch::create([
            'product_id' => $product->id, 'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id, 'batch_number' => 'TP-OLD',
            'quantity_received' => 20, 'quantity_remaining' => 20,
            'cost_per_unit' => 20, 'selling_price_per_unit' => 35,
            'expiry_date' => now()->subMonth()->toDateString(),
            'purchase_date' => now(),
        ]);
        PurchaseBatch::create([
            'product_id' => $product->id, 'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id, 'batch_number' => 'TP-NEW',
            'quantity_received' => 5, 'quantity_remaining' => 5,
            'cost_per_unit' => 20, 'selling_price_per_unit' => 35,
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_date' => now(),
        ]);

        $this->actingAs($this->user)->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('Expired Toothpaste')
            ->assertSee('minimum 10');

        $this->assertSame(5, $product->fresh()->current_stock);
    }
}
