<?php

namespace Tests\Feature;

use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use Database\Seeders\SupplierCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Supplier catalogue seeder.
 *
 * Three things are being defended here. First, that it files every invoiced
 * item under a real category - a catalogue row pointing at nothing is worse
 * than a missing row, because it looks complete. Second, that it is idempotent:
 * a seeder that duplicates rows every time it runs turns a catalogue into noise
 * within a week. Third, that the packing it writes is the packing the invoice
 * describes, because every sale, purchase and stock count divides by it.
 */
class SupplierCatalogueSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function seedCatalogue(): void
    {
        $this->seed(SupplierCatalogueSeeder::class);
    }

    /** The data the seeder carries, read without running it. */
    private function constant(string $name): array
    {
        return (new \ReflectionClass(SupplierCatalogueSeeder::class))
            ->getReflectionConstant($name)
            ->getValue();
    }

    public function test_it_creates_every_drug_from_the_invoices(): void
    {
        $this->seedCatalogue();

        $rows = array_merge($this->constant('DRUGS'), $this->constant('LATE_DRUGS'));

        foreach ($rows as $row) {
            // whereNull, not where('strength', null): the latter compiles to
            // "strength = NULL" and matches nothing.
            $query = Drug::where('name', $row['name']);
            $found = ($row['strength'] === null
                ? $query->whereNull('strength')
                : $query->where('strength', $row['strength']))->exists();

            $this->assertTrue($found, "Missing drug: {$row['name']} " . ($row['strength'] ?? ''));
        }

        $this->assertSame(count($rows), Drug::count());
    }

    public function test_it_creates_every_product_from_the_invoices(): void
    {
        $this->seedCatalogue();

        $rows = array_merge($this->constant('PRODUCTS'), $this->constant('LATE_PRODUCTS'));

        foreach ($rows as $row) {
            $this->assertTrue(Product::where('sku', $row['sku'])->exists(), "Missing product SKU: {$row['sku']}");
        }

        $this->assertSame(count($rows), Product::count());
    }

    public function test_it_creates_the_new_categories_and_reuses_the_existing_ones(): void
    {
        $this->seedCatalogue();

        foreach ([
            'Anthelmintics', 'Neurology and Antiepileptics', 'Respiratory',
            'Dermatology', 'Ophthalmology', 'Gynecological', 'Gastrointestinal',
            'Hormonal Medications', 'Cardiovascular', 'Antivirals', 'ENT', 'Other',
        ] as $name) {
            $this->assertTrue(DrugCategory::where('name', $name)->exists(), "Missing drug category: {$name}");
        }

        $this->assertTrue(ProductCategory::where('name', 'Vitamins and Supplements')->exists());

        // Existing categories must be reused, not duplicated.
        $this->assertSame(1, DrugCategory::where('name', 'Antibiotics')->count());
        $this->assertSame(1, DrugCategory::where('name', 'Chronic Care')->count());
        $this->assertSame(1, ProductCategory::where('name', 'Medical Supplies')->count());
    }

    /** Nothing may point at a category that does not exist. */
    public function test_no_row_points_at_a_missing_category(): void
    {
        $this->seedCatalogue();

        foreach (Drug::all() as $drug) {
            $this->assertNotNull(
                DrugCategory::find($drug->category_id),
                "{$drug->name} points at a category that does not exist"
            );
        }

        foreach (Product::all() as $product) {
            $this->assertNotNull(
                ProductCategory::find($product->category_id),
                "{$product->name} points at a category that does not exist"
            );
        }
    }

    public function test_the_two_albendazole_lines_become_one_drug_and_two_batches_later(): void
    {
        // "Alwo of 100" and "Aldaz 10x5" are the same active ingredient and
        // strength under two brands. One catalogue row, two batches.
        $this->seedCatalogue();

        $this->assertSame(1, Drug::where('name', 'Albendazole')->where('strength', '400mg')->count());
    }

    /**
     * The three shapes the invoice writes packing in must come out as the right
     * number of rungs, with the right factors. Every sale divides by these.
     */
    public function test_the_invoice_packing_becomes_the_right_rungs(): void
    {
        $this->seedCatalogue();

        // "2*14": a pack of 2 strips of 14 tablets.
        $valcodin = Drug::where('name', 'Valcodin')->firstOrFail();
        $this->assertSame(
            [
                ['key' => 'top', 'name' => 'pack', 'factor' => 28, 'sellable' => true],
                ['key' => 'middle', 'name' => 'strip', 'factor' => 14, 'sellable' => true],
                ['key' => 'base', 'name' => 'tablet', 'factor' => 1, 'sellable' => true],
            ],
            $valcodin->packingLevels()
        );

        // "of 30": a pack of 30 capsules, no strip named.
        $onegaba = Drug::where('name', 'OneGaba')->firstOrFail();
        $this->assertSame(
            [
                ['key' => 'top', 'name' => 'pack', 'factor' => 30, 'sellable' => true],
                ['key' => 'base', 'name' => 'capsule', 'factor' => 1, 'sellable' => true],
            ],
            $onegaba->packingLevels()
        );

        // A bottle has nothing inside it that is sold separately: one rung.
        $kepprolin = Drug::where('name', 'Kepprolin')->firstOrFail();
        $this->assertSame(
            [['key' => 'top', 'name' => 'bottle', 'factor' => 1, 'sellable' => true]],
            $kepprolin->packingLevels()
        );

        // "10 to 10" is the same notation as 10*10: Dizinil is 10 strips of 10.
        $dizinil = Drug::where('name', 'Dizinil')->firstOrFail();
        $this->assertSame(100, $dizinil->unitsPerTop());
        $this->assertSame(10, $dizinil->packingLevels()[1]['factor']);

        // A tin is a container, but it holds tablets. The tablet is the base
        // unit: buying one tin is 30 tablets, not one unit.
        $folic = Drug::where('name', 'Folic Acid')->firstOrFail();
        $this->assertSame(
            [
                ['key' => 'top', 'name' => 'tin', 'factor' => 30, 'sellable' => true],
                ['key' => 'base', 'name' => 'tablet', 'factor' => 1, 'sellable' => true],
            ],
            $folic->packingLevels()
        );

        // "1*10": one strip of ten. A pack is ten tablets, not ten packs.
        $imatox = Drug::where('name', 'Imatox')->firstOrFail();
        $this->assertSame(10, $imatox->unitsPerTop());
        $this->assertSame(10, $imatox->packingLevels()[1]['factor']);
        $this->assertSame('tablet', $imatox->packingLevels()[2]['name']);

        // "of 20 vials": Cefe-Denk is a pack of 20 vials, and a vial can be
        // sold on its own.
        $cefe = Drug::where('name', 'Cefe-Denk')->firstOrFail();
        $this->assertSame(20, $cefe->unitsPerTop());
        $this->assertSame(
            ['key' => 'base', 'name' => 'vial', 'factor' => 1, 'sellable' => true],
            $cefe->packingLevels()[1]
        );
    }

    public function test_it_does_not_duplicate_a_drug_that_already_exists(): void
    {
        // Azithromycin 500 mg is already in the catalogue. The invoice also
        // carries it, so the seeder must recognise the match instead of adding
        // a second row that competes with the first in search.
        $existingCategory = DrugCategory::create(['name' => 'Antibiotics']);

        Drug::create([
            'name' => 'Azithromycin', 'strength' => '500 mg', 'unit_of_measure' => 'tablet',
            'units_per_strip' => 3, 'strips_per_pack' => 1, 'category_id' => $existingCategory->id,
        ]);

        $this->seedCatalogue();

        $this->assertSame(1, Drug::where('name', 'Azithromycin')->where('strength', '500 mg')->count());
        $this->assertSame(1, Drug::where('name', 'Azithromycin')->where('strength', '250mg')->count());

        // The invoice says "of 3", so the row becomes a pack of three tablets.
        // The old 3-per-strip geometry described the same physical pack.
        $existing = Drug::where('name', 'Azithromycin')->where('strength', '500 mg')->firstOrFail();
        $this->assertSame('pack', $existing->unit_top);
        $this->assertSame(3, $existing->unitsPerTop());
        $this->assertNull($existing->unit_middle);
    }

    /**
     * "500 mg" and "500mg" are one tablet. Matching the raw strings is how a
     * second Metformin appeared beside the first: the demo catalogue spelled it
     * with a space and the invoice did not.
     */
    public function test_a_strength_spelled_differently_is_still_the_same_drug(): void
    {
        $category = DrugCategory::create(['name' => 'Chronic Care']);

        Drug::create([
            'name' => 'Metformin', 'strength' => '500 mg', 'category_id' => $category->id,
        ]);

        $this->seedCatalogue();

        $this->assertSame(1, Drug::where('name', 'Metformin')->count());
        $this->assertSame('500 mg', Drug::where('name', 'Metformin')->value('strength'));
        $this->assertSame(100, Drug::where('name', 'Metformin')->firstOrFail()->unitsPerTop());
    }

    /**
     * The brand-only lines have no strength on the invoice. A naive lookup
     * would treat them as new on every run and report "created" forever, so
     * this checks the second run changes nothing.
     */
    public function test_rows_without_a_strength_are_not_recreated_each_run(): void    {
        $this->seedCatalogue();

        $before = Drug::whereNull('strength')->pluck('id')->sort()->values()->all();

        $this->seedCatalogue();

        $this->assertSame(
            $before,
            Drug::whereNull('strength')->pluck('id')->sort()->values()->all(),
            'A brand with no stated strength was created again on the second run.'
        );
        $this->assertGreaterThan(
            8,
            count($before),
            'The invoices carry many brand-only lines; only a handful were found.'
        );
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $this->seedCatalogue();

        $drugs = Drug::count();
        $products = Product::count();
        $drugCategories = DrugCategory::count();
        $productCategories = ProductCategory::count();

        $snapshot = Drug::orderBy('id')->get([
            'id', 'name', 'strength', 'category_id', 'unit_top', 'top_holds',
            'unit_middle', 'middle_holds', 'unit_base', 'units_per_strip', 'strips_per_pack',
        ])->toArray();

        $this->seedCatalogue();

        $this->assertSame($drugs, Drug::count(), 'A second run created duplicate drugs.');
        $this->assertSame($products, Product::count(), 'A second run created duplicate products.');
        $this->assertSame($drugCategories, DrugCategory::count(), 'A second run created duplicate categories.');
        $this->assertSame($productCategories, ProductCategory::count());
        $this->assertEquals(
            $snapshot,
            Drug::orderBy('id')->get([
                'id', 'name', 'strength', 'category_id', 'unit_top', 'top_holds',
                'unit_middle', 'middle_holds', 'unit_base', 'units_per_strip', 'strips_per_pack',
            ])->toArray(),
            'A second run altered existing rows.'
        );
    }

    public function test_brands_without_a_stated_ingredient_keep_it_blank(): void
    {
        // These carry a brand but no active ingredient on the invoice.
        // Inventing one would be guessing at a medicine.
        $this->seedCatalogue();

        $this->assertNull(Drug::where('name', 'Libitus Cough Syrup')->first()->generic_name);
        $this->assertNull(Drug::where('name', 'Netracort Ointment')->first()->strength);
        $this->assertNull(Drug::where('name', 'Zoxan D')->first()->generic_name);
        $this->assertNull(Drug::where('name', 'Medofed')->first()->generic_name);
        $this->assertSame('Fluconazole', Drug::where('name', 'Zocon Eye Drop')->first()->generic_name);
    }

    /**
     * Nothing is invented: where the paper says how many are inside, the drug
     * is divided; where it does not, the drug is sold whole.
     */
    public function test_unknown_contents_are_sold_whole_rather_than_guessed(): void
    {
        $this->seedCatalogue();

        // Vigafos is billed by the pack and the invoice never says how many
        // sachets are inside, so there is exactly one rung.
        $vigafos = Drug::where('name', 'Vigafos')->firstOrFail();
        $this->assertCount(1, $vigafos->packingLevels());
        $this->assertSame('pack', $vigafos->packingLevels()[0]['name']);

        // A tube of cream is a single sellable unit, never a "strip of cream".
        $foban = Drug::where('name', 'Foban')->firstOrFail();
        $this->assertSame(
            [['key' => 'top', 'name' => 'tube', 'factor' => 1, 'sellable' => true]],
            $foban->packingLevels()
        );
    }

    public function test_products_are_active_and_have_a_reorder_level(): void
    {
        $this->seedCatalogue();

        foreach (Product::all() as $product) {
            $this->assertTrue((bool) $product->active, "{$product->name} is not active.");
            $this->assertGreaterThan(0, (float) $product->minimum_stock);
        }
    }

    public function test_the_unpriceable_rezumin_line_says_so(): void
    {
        // That line had a quantity but no batch, expiry or price, so it can be
        // listed but not purchased. Whoever buys it needs to know why.
        $this->seedCatalogue();

        $this->assertStringContainsString(
            'no batch, expiry or price',
            (string) Product::where('sku', 'VS-REZU-30')->first()->description
        );
    }
}

