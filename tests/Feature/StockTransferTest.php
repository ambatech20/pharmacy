<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $source;
    protected Branch $destination;
    protected Branch $other;
    protected Supplier $supplier;
    protected Drug $drug;
    protected Product $product;
    protected User $owner;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = Branch::create(['name' => 'Source Branch']);
        $this->destination = Branch::create(['name' => 'Destination Branch']);
        $this->other = Branch::create(['name' => 'Other Branch']);
        $this->supplier = Supplier::create(['name' => 'Test Supplier']);

        $drugCategory = DrugCategory::create(['name' => 'Antibiotics']);
        $this->drug = Drug::create([
            'name' => 'Amoxicillin',
            'strength' => '500mg',
            'form' => 'capsule',
            'kind' => 'medicine',
            'category_id' => $drugCategory->id,
            'unit_top' => 'pack',
            'top_holds' => 2,
            'unit_middle' => 'strip',
            'middle_holds' => 10,
            'unit_base' => 'capsule',
            'minimum_stock_level' => 1,
        ]);

        $productCategory = ProductCategory::create(['name' => 'Consumables']);
        $this->product = Product::create([
            'name' => 'Test Mask',
            'sku' => 'MASK-TEST',
            'category_id' => $productCategory->id,
            'unit_top' => 'pack',
            'top_holds' => 12,
            'unit_base' => 'piece',
            'minimum_stock' => 1,
            'active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'username' => 'transfer-owner',
            'email' => 'transfer-owner@example.com',
            'password' => 'secret1234',
            'role' => 'owner_admin',
            'branch_id' => null,
        ]);

        $this->manager = User::create([
            'name' => 'Manager',
            'username' => 'transfer-manager',
            'email' => 'transfer-manager@example.com',
            'password' => 'secret1234',
            'role' => 'branch_manager',
            'branch_id' => $this->source->id,
        ]);
    }

    private function drugBatch(int $quantity = 40, ?Branch $branch = null): PurchaseBatch
    {
        return PurchaseBatch::create([
            'drug_id' => $this->drug->id,
            'supplier_id' => $this->supplier->id,
            'branch_id' => ($branch ?? $this->source)->id,
            'batch_number' => 'DRUG-'.uniqid(),
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
            'cost_per_unit' => 1,
            'selling_price_per_unit' => 2,
            'purchase_date' => now(),
            'expiry_date' => now()->addYear(),
        ]);
    }

    private function productBatch(int $quantity = 24, ?Branch $branch = null): PurchaseBatch
    {
        return PurchaseBatch::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'branch_id' => ($branch ?? $this->source)->id,
            'batch_number' => 'PRODUCT-'.uniqid(),
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
            'cost_per_unit' => 1,
            'selling_price_per_unit' => 2,
            'purchase_date' => now(),
            'expiry_date' => now()->addYear(),
        ]);
    }

    public function test_drug_transfer_converts_the_selected_packing_unit_to_base_units(): void
    {
        $batch = $this->drugBatch(40);

        $this->actingAs($this->manager)->post(route('transfers.store'), [
            'from_branch_id' => $this->source->id,
            'to_branch_id' => $this->destination->id,
            'item_type' => 'drug',
            'item_id' => $this->drug->id,
            'quantity' => 1,
            'quantity_unit' => 'top',
        ])->assertRedirect(route('transfers.index'))->assertSessionHasNoErrors();

        // One pack = 2 strips of 10 = 20 capsules.
        $this->assertSame(20, $batch->fresh()->quantity_remaining);
        $this->assertSame(
            20,
            (int) PurchaseBatch::where('drug_id', $this->drug->id)
                ->where('branch_id', $this->destination->id)
                ->sum('quantity_remaining')
        );
    }

    public function test_product_transfer_converts_the_selected_packing_unit_to_base_units(): void
    {
        $batch = $this->productBatch(24);

        $this->actingAs($this->manager)->post(route('transfers.store'), [
            'from_branch_id' => $this->source->id,
            'to_branch_id' => $this->destination->id,
            'item_type' => 'product',
            'item_id' => $this->product->id,
            'quantity' => 1,
            'quantity_unit' => 'top',
        ])->assertRedirect(route('transfers.index'))->assertSessionHasNoErrors();

        // One pack = 12 pieces, not one piece.
        $this->assertSame(12, $batch->fresh()->quantity_remaining);
        $this->assertSame(
            12,
            (int) PurchaseBatch::where('product_id', $this->product->id)
                ->where('branch_id', $this->destination->id)
                ->sum('quantity_remaining')
        );
    }

    public function test_old_pack_word_still_maps_to_the_top_rung(): void
    {
        $batch = $this->drugBatch(40);

        $this->actingAs($this->manager)->post(route('transfers.store'), [
            'from_branch_id' => $this->source->id,
            'to_branch_id' => $this->destination->id,
            'item_type' => 'drug',
            'item_id' => $this->drug->id,
            'quantity' => 1,
            'quantity_unit' => 'pack',
        ])->assertRedirect(route('transfers.index'))->assertSessionHasNoErrors();

        $this->assertSame(20, $batch->fresh()->quantity_remaining);
    }

    public function test_branch_manager_cannot_transfer_out_of_another_branch(): void
    {
        $batch = $this->drugBatch(40, $this->other);

        $this->actingAs($this->manager)->post(route('transfers.store'), [
            'from_branch_id' => $this->other->id,
            'to_branch_id' => $this->destination->id,
            'item_type' => 'drug',
            'item_id' => $this->drug->id,
            'quantity' => 1,
            'quantity_unit' => 'top',
        ])->assertSessionHasErrors('from_branch_id');

        $this->assertSame(40, $batch->fresh()->quantity_remaining);
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_owner_with_no_branch_can_transfer_out_of_any_branch(): void
    {
        $batch = $this->drugBatch(40, $this->other);

        $this->actingAs($this->owner)->post(route('transfers.store'), [
            'from_branch_id' => $this->other->id,
            'to_branch_id' => $this->destination->id,
            'item_type' => 'drug',
            'item_id' => $this->drug->id,
            'quantity' => 1,
            'quantity_unit' => 'top',
        ])->assertRedirect(route('transfers.index'))->assertSessionHasNoErrors();

        $this->assertSame(20, $batch->fresh()->quantity_remaining);
    }

    public function test_a_unit_the_item_does_not_have_is_rejected(): void
    {
        $this->drugBatch(40);

        $this->actingAs($this->manager)->post(route('transfers.store'), [
            'from_branch_id' => $this->source->id,
            'to_branch_id' => $this->destination->id,
            'item_type' => 'drug',
            'item_id' => $this->drug->id,
            'quantity' => 1,
            'quantity_unit' => 'bottle',
        ])->assertSessionHasErrors('quantity_unit');

        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_transfer_form_offers_the_items_own_packing_units(): void
    {
        $this->actingAs($this->manager)
            ->get(route('transfers.create'))
            ->assertOk()
            ->assertSee('name="from_branch_id" value="'.$this->source->id.'"', false)
            ->assertSee('"key":"top"', false)
            ->assertSee('"name":"pack"', false)
            ->assertSee('"name":"capsule"', false);
    }

    public function test_transfer_index_shows_the_quantity_in_packing_units(): void
    {
        $this->drugBatch(40);

        $this->actingAs($this->manager)->post(route('transfers.store'), [
            'from_branch_id' => $this->source->id,
            'to_branch_id' => $this->destination->id,
            'item_type' => 'drug',
            'item_id' => $this->drug->id,
            'quantity' => 1,
            'quantity_unit' => 'top',
        ])->assertRedirect(route('transfers.index'));

        $this->get(route('transfers.index'))
            ->assertOk()
            ->assertSee('1 pack');
    }
}
