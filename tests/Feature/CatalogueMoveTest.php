<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\IssuedItem;
use App\Models\Product;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Services\CatalogueMoveService;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SupplierCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Medical supplies, vitamins and infant formula belong on the drugs side for
 * tax relief. The move must keep the stock and the sales history that were
 * already recorded against the product.
 */
class CatalogueMoveTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_moves_supplies_and_vitamins_to_drugs_and_keeps_stock_and_sales(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->seed(SupplierCatalogueSeeder::class);

        $service = app(CatalogueMoveService::class);
        $preview = $service->preview();

        $this->assertCount(37, $preview);
        $this->assertSame([], array_values(array_filter(array_column($preview, 'clash'))));

        // Give the gloves a batch and a sale before the move.
        $gloves = Product::where('sku', 'MS-GLV-L')->firstOrFail();
        $branch = Branch::create(['name' => 'Main Branch']);
        $supplier = Supplier::create(['name' => 'Medi Supply']);

        $batch = PurchaseBatch::create([
            'product_id' => $gloves->id,
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'batch_number' => 'GLV-1',
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'cost_per_unit' => 8,
            'selling_price_per_unit' => 12,
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_date' => now()->toDateString(),
        ]);

        $issued = IssuedItem::create([
            'purchase_batch_id' => $batch->id,
            'product_id' => $gloves->id,
            'branch_id' => $branch->id,
            'quantity_issued' => 1,
            'selling_price_per_unit' => 12,
            'total_amount' => 12,
            'issue_date' => now(),
        ]);

        $summary = $service->apply();

        $this->assertSame(37, $summary['moved']);
        $this->assertSame(0, $summary['merged']);
        $this->assertSame(0, $summary['skipped']);

        $drug = Drug::where('sku', 'MS-GLV-L')->firstOrFail();
        $this->assertSame('medical_supply', $drug->kind);
        $this->assertSame(10, $drug->current_stock);
        $this->assertSame(200, (int) $drug->minimum_stock_level);
        $this->assertSame('piece', $drug->topUnitLabel());

        $this->assertNull($batch->fresh()->product_id);
        $this->assertSame($drug->id, $batch->fresh()->drug_id);
        $this->assertNull($issued->fresh()->product_id);
        $this->assertSame($drug->id, $issued->fresh()->purchaseBatch->drug_id);

        $this->assertFalse(Product::where('sku', 'MS-GLV-L')->exists());

        // A box of 50 masks is a pack of 50 pieces.
        $mask = Drug::where('sku', 'MS-FAC-050')->firstOrFail();
        $this->assertSame('pack', $mask->topUnitLabel());
        $this->assertSame(50, $mask->unitsPerTop());
        $this->assertSame('piece', $mask->baseUnitLabel());
        $this->assertSame(['top', 'base'], $mask->sellableUnitKeys());

        // A bottle of antiseptic is sold whole.
        $dettol = Drug::where('sku', 'HY-DET-250')->firstOrFail();
        $this->assertSame('bottle', $dettol->topUnitLabel());
        $this->assertSame(1, $dettol->unitsPerTop());

        // A pot of 30 capsules is a pack of 30 capsules.
        $revamp = Drug::where('sku', 'VS-RVPC-30')->firstOrFail();
        $this->assertSame('supplement', $revamp->kind);
        $this->assertSame(30, $revamp->unitsPerTop());
        $this->assertSame('capsule', $revamp->baseUnitLabel());
        $this->assertSame(['top'], $revamp->sellableUnitKeys());

        // The empty product categories are gone; the retail ones remain.
        $this->assertDatabaseMissing('product_categories', ['name' => 'Medical Supplies']);
        $this->assertDatabaseMissing('product_categories', ['name' => 'Vitamins and Supplements']);
        $this->assertDatabaseHas('product_categories', ['name' => 'Hygiene Products']);

        // Running it again changes nothing.
        $again = $service->apply();
        $this->assertSame(0, $again['moved']);
        $this->assertSame(0, $again['merged']);
        $this->assertSame(0, $again['skipped']);
    }

    public function test_a_product_with_a_different_base_unit_is_left_for_a_person_to_resolve(): void
    {
        $this->seed(CatalogSeeder::class);

        // A drug with the same name but counted in tablets: merging the
        // product's pieces into it would silently add unlike things.
        $gloves = Product::where('sku', 'MS-GLV-L')->firstOrFail();
        $category = \App\Models\DrugCategory::create(['name' => 'Medical Supplies']);
        Drug::create([
            'name' => $gloves->name,
            'category_id' => $category->id,
            'unit_of_measure' => 'tablet',
            'units_per_strip' => 1,
            'strips_per_pack' => 1,
        ]);

        $summary = app(CatalogueMoveService::class)->apply();

        $this->assertSame(1, $summary['skipped']);
        $this->assertTrue(Product::where('sku', 'MS-GLV-L')->exists());
        $this->assertStringContainsString('left alone', $summary['warnings'][0]);
    }
}

