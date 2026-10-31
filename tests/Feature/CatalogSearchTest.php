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
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The catalogue search endpoint behind the sale screen.
 *
 * This endpoint used to exist, throw a 500 on every call because
 * Illuminate\Support\Str was never imported, and be covered by nothing at all:
 * no test hit the route, and the sale screen filtered a preloaded <select>
 * instead of calling it. It is now the thing the cashier actually uses, so it
 * is tested directly - including that it returns drugs AND products, because
 * the old preloaded list offered only drugs even though dispense.store() has
 * always accepted products.
 */
class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected Supplier $supplier;
    protected User $clerk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->supplier = Supplier::create(['name' => 'Medi Supply']);
        $this->clerk = User::create([
            'name' => 'Clerk', 'username' => 'clerk', 'email' => 'clerk@example.com',
            'password' => 'secret1234', 'role' => 'pharmacist_clerk',
            'branch_id' => $this->branch->id,
        ]);
    }

    protected function drug(string $name, string $strength = '500mg'): Drug
    {
        return Drug::create([
            'name' => $name, 'strength' => $strength, 'unit_of_measure' => 'capsule',
            'units_per_strip' => 10, 'strips_per_pack' => 20,
            'category_id' => DrugCategory::create(['name' => 'General'])->id,
        ]);
    }

    protected function stock(Drug $drug, string $batch, int $remaining, string $price = '15.00'): void
    {
        PurchaseBatch::create([
            'drug_id' => $drug->id, 'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id, 'batch_number' => $batch,
            'quantity_received' => $remaining, 'quantity_remaining' => $remaining,
            'cost_per_unit' => 10, 'selling_price_per_unit' => $price,
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_date' => now()->toDateString(),
        ]);
    }

    /** The regression that let a 500 live here unnoticed. */
    public function test_it_returns_json_rather_than_erroring(): void
    {
        $this->drug('Paracetamol');

        $response = $this->actingAs($this->clerk)->getJson(route('search.drug', ['q' => 'Parac']));

        $response->assertOk()->assertJsonStructure(['results']);
    }

    public function test_a_match_carries_everything_the_cart_line_needs(): void
    {
        $drug = $this->drug('Paracetamol');
        $this->stock($drug, 'B1', 40, '15.50');

        $row = $this->actingAs($this->clerk)
            ->getJson(route('search.drug', ['q' => 'Parac']))
            ->assertOk()
            ->json('results.0');

        // key and label drive the <option>; the rest drive units and price.
        $this->assertSame('drug:' . $drug->id, $row['key']);
        $this->assertSame('Paracetamol (500mg)', $row['label']);
        // Biggest first, each with how many base units it holds:
        // units_per_strip 10 x strips_per_pack 20 = a 200-capsule pack.
        $this->assertSame([
            ['key' => 'top', 'name' => 'pack', 'factor' => 200],
            ['key' => 'middle', 'name' => 'strip', 'factor' => 10],
            ['key' => 'base', 'name' => 'capsule', 'factor' => 1],
        ], $row['levels']);
        $this->assertSame(15.5, $row['price']);
        $this->assertSame('#1 - 40 left', $row['marker']);
    }

    public function test_products_are_searchable_and_keyed_differently_from_drugs(): void
    {
        // The old preloaded <select> listed only drugs, so a product could not
        // be sold from the screen even though store() accepts one.
        Product::create([
            'name' => 'Surgical Face Mask', 'sku' => 'FM-001',
            'category_id' => ProductCategory::create(['name' => 'Consumables'])->id,
        ]);

        $row = $this->actingAs($this->clerk)
            ->getJson(route('search.drug', ['q' => 'Face Mask']))
            ->assertOk()
            ->json('results.0');

        $this->assertStringStartsWith('product:', $row['key']);
        $this->assertSame('Surgical Face Mask', $row['label']);
        // Whole pieces: one rung, no strip/pack geometry. Products use the
        // same packing model as drugs now, so the single rung is the top one.
        $this->assertSame([
            ['key' => 'top', 'name' => 'piece', 'factor' => 1],
        ], $row['levels']);
    }

    public function test_a_drug_id_and_a_product_id_may_be_the_same_number(): void
    {
        // The catalogue_key prefix exists precisely because these two tables
        // number independently.
        $drug = $this->drug('Zinc');
        $product = Product::create([
            'name' => 'Zinc Cream', 'sku' => 'ZC-1',
            'category_id' => ProductCategory::create(['name' => 'Consumables'])->id,
        ]);

        $keys = collect($this->actingAs($this->clerk)->getJson(route('search.drug', ['q' => 'Zinc']))->json('results'))
            ->pluck('key')->sort()->values()->all();

        $this->assertSame(
            ['drug:' . $drug->id, 'product:' . $product->id],
            $keys
        );
    }

    public function test_inactive_products_are_not_offered(): void
    {
        Product::create([
            'name' => 'Old Mask', 'sku' => 'OLD-1', 'active' => false,
            'category_id' => ProductCategory::create(['name' => 'Consumables'])->id,
        ]);

        $this->actingAs($this->clerk)->getJson(route('search.drug', ['q' => 'Old Mask']))
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_a_wildcard_is_searched_for_literally(): void
    {
        // Unescaped, "%" would match every row in the catalogue.
        $this->drug('Paracetamol');
        $this->drug('Amoxicillin');

        $this->actingAs($this->clerk)->getJson(route('search.drug', ['q' => '%']))
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_one_character_does_not_return_the_whole_catalogue(): void
    {
        $this->drug('Paracetamol');
        $this->drug('Amoxicillin');

        $this->actingAs($this->clerk)->getJson(route('search.drug', ['q' => 'a']))
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_generic_name_and_strength_are_searchable(): void
    {
        $drug = $this->drug('Augmentin', '625mg');
        $drug->update(['generic_name' => 'Amoxicillin']);

        $byGeneric = $this->actingAs($this->clerk)->getJson(route('search.drug', ['q' => 'Amoxic']))->json('results');
        $this->assertSame('drug:' . $drug->id, $byGeneric[0]['key']);

        $byStrength = $this->actingAs($this->clerk)->getJson(route('search.drug', ['q' => '625']))->json('results');
        $this->assertSame('drug:' . $drug->id, $byStrength[0]['key']);
    }

    public function test_it_is_closed_to_signed_out_visitors(): void
    {
        $this->getJson(route('search.drug', ['q' => 'Parac']))->assertUnauthorized();
    }

    /**
     * The sale screen must list the catalogue in its dropdown - that is how the
     * counter is used - but must not carry every item's packing, price and
     * stock marker, which are fetched when an item is picked.
     */
    public function test_the_sale_screen_lists_items_without_embedding_their_numbers(): void
    {
        $drug = $this->drug('Paracetamol');
        $this->drug('Amoxicillin');

        $html = $this->actingAs($this->clerk)->get(route('dispense.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="drug:' . $drug->id . '"', $html);
        $this->assertStringContainsString('Paracetamol', $html);

        // The preload shape was {"drug:1":{"levels":... - that must be gone.
        $this->assertStringNotContainsString('"drug:' . $drug->id . '":{"levels"', $html);
        // @json escapes slashes, so match the path rather than the full URL.
        $this->assertStringContainsString('item-data', $html);
    }

    /** Picking from the dropdown fetches that one item's units and price. */
    public function test_picking_a_drug_returns_its_units_price_and_marker(): void
    {
        $drug = $this->drug('Paracetamol');
        $this->stock($drug, 'B1', 40, '15.50');

        $this->actingAs($this->clerk)
            ->getJson(route('item.data', ['key' => 'drug:' . $drug->id]))
            ->assertOk()
            ->assertJsonPath('key', 'drug:' . $drug->id)
            ->assertJsonPath('label', 'Paracetamol (500mg)')
            ->assertJsonPath('price', 15.5)
            ->assertJsonPath('marker', '#1 - 40 left')
            ->assertJsonCount(3, 'levels');
    }

    public function test_picking_a_product_returns_its_units(): void
    {
        $product = Product::create([
            'name' => 'Surgical Face Mask', 'sku' => 'FM-001',
            'category_id' => ProductCategory::create(['name' => 'Consumables'])->id,
        ]);

        $this->actingAs($this->clerk)
            ->getJson(route('item.data', ['key' => 'product:' . $product->id]))
            ->assertOk()
            ->assertJsonPath('key', 'product:' . $product->id)
            ->assertJsonPath('label', 'Surgical Face Mask');
    }

    public function test_an_unknown_item_key_is_not_found(): void
    {
        // A blank key is an empty answer, not an error: the route smoke test
        // visits every GET screen without parameters.
        $this->actingAs($this->clerk)
            ->getJson(route('item.data'))
            ->assertOk()
            ->assertExactJson([]);

        $this->actingAs($this->clerk)
            ->getJson(route('item.data', ['key' => 'drug:999999']))
            ->assertNotFound();

        $this->actingAs($this->clerk)
            ->getJson(route('item.data', ['key' => 'nonsense']))
            ->assertNotFound();
    }
}
