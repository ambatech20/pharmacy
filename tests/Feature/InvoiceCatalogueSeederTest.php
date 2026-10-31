<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\InvoiceCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 163-row invoice catalogue, imported without losing the unit logic.
 *
 * These tests pin four things:
 *   - every row imports, and importing twice creates nothing new;
 *   - every confirmed count is arithmetically sound and still uses the
 *     top/middle/base rungs the rest of the app reads;
 *   - the invoice corrections (Amlo Denk, Valcodin, Aspirin, Imatox, gauze)
 *     are applied without touching stock, SKU or the reorder level;
 *   - a row created by the import can be bought and sold by its own rungs,
 *     at the price the rung implies.
 */
class InvoiceCatalogueSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);
    }

    private function import(): void
    {
        $this->seed(InvoiceCatalogueSeeder::class);
    }

    private function drug(string $name, ?string $strength = null): Drug
    {
        return Drug::where('name', $name)
            ->when($strength !== null, fn ($query) => $query->where('strength', $strength))
            ->firstOrFail();
    }

    public function test_every_catalogue_row_is_imported_as_a_drug(): void
    {
        $this->import();

        $this->assertSame(163, Drug::count());
        $this->assertSame(0, Product::count(), 'The catalogue never creates a Products-side row.');
        $this->assertSame(100, (int) Drug::where('name', 'Gauze Swab')->firstOrFail()->minimum_stock_level);
    }

    public function test_importing_twice_changes_nothing(): void
    {
        $this->import();
        $first = Drug::count();

        $this->import();

        $this->assertSame($first, Drug::count());
        $this->assertSame(163, Drug::count());

        $keys = Drug::all()
            ->map(fn (Drug $drug) => strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $drug->name . '|' . $drug->strength)))
            ->countBy();

        $this->assertTrue($keys->every(fn (int $count) => $count === 1), 'A re-run must not create a second row for the same name and strength.');
    }

    public function test_every_imported_row_keeps_a_sound_packing_chain(): void
    {
        $this->import();

        foreach (Drug::with('category')->get() as $drug) {
            $levels = $drug->packingLevels();

            $this->assertNotEmpty($levels, "{$drug->name} has no unit to buy or sell.");
            $this->assertSame(1, end($levels)['factor'], "{$drug->name} does not count down to a single base unit.");
            $this->assertSame($drug->unitsPerTop(), $levels[0]['factor'], "{$drug->name} top count does not match its rungs.");

            $factors = array_column($levels, 'factor');
            $sorted = $factors;
            rsort($sorted);
            $this->assertSame($sorted, $factors, "{$drug->name} rungs are not biggest-first.");

            $names = array_column($levels, 'name');
            $this->assertSame(
                count($names),
                count(array_unique($names)),
                "{$drug->name} has the same unit on two rungs: " . implode(' / ', $names)
            );

            $this->assertTrue(
                collect($levels)->contains(fn (array $level) => $level['sellable']),
                "{$drug->name} has no sellable rung."
            );

            if (count($levels) > 1
                && in_array(strtolower((string) $drug->form), ['tablet', 'capsule', 'sachet', 'suppository'], true)) {
                $base = end($levels)['name'];
                $this->assertNotContains($base, Drug::CONTAINER_UNITS, "{$drug->name} keeps stock in a {$base}.");
            }
        }
    }

    public function test_the_invoice_corrections_are_applied(): void
    {
        // The row the old seeder got wrong; the import must correct it in
        // place rather than leaving 5+5 beside the two real strengths.
        Drug::create([
            'name' => 'Amlo Denk',
            'strength' => '5+5',
            'form' => 'tablet',
            'category_id' => DrugCategory::firstOrCreate(['name' => 'Cardiovascular'])->id,
            'unit_top' => 'pack',
            'top_holds' => 10,
            'unit_middle' => 'strip',
            'middle_holds' => 10,
            'unit_base' => 'tablet',
        ]);

        // The old Aspirin row was sold whole.
        $aspirin = Drug::create([
            'name' => 'Aspirin',
            'strength' => '81mg',
            'form' => 'tablet',
            'category_id' => DrugCategory::firstOrCreate(['name' => 'Analgesics and Antipyretics'])->id,
            'unit_top' => 'pack',
            'top_holds' => 1,
            'unit_base' => 'pack',
        ]);

        $this->import();

        $five = $this->drug('Amlo Denk', '5mg');
        $ten = $this->drug('Amlo Denk', '10mg');

        $this->assertSame(0, Drug::where('name', 'Amlo Denk')->where('strength', '5+5')->count());
        $this->assertSame(2, Drug::where('name', 'Amlo Denk')->count());
        $this->assertSame(50, $five->unitsPerTop());
        $this->assertNull($five->unit_middle);
        $this->assertSame(50, $ten->unitsPerTop());
        $this->assertSame('strip', $ten->unit_middle);

        // Valcodin is amlodipine/valsartan, not an antibiotic.
        $valcodin = $this->drug('Valcodin', '5mg/160mg');
        $this->assertSame('Amlodipine/Valsartan', $valcodin->generic_name);
        $this->assertSame('Cardiovascular', $valcodin->category->name);
        $this->drug('Valcodin', '10mg/160mg');

        // Aspirin 10*10, counted down to the tablet.
        $aspirin->refresh();
        $this->assertSame('tablet', $aspirin->unit_base);
        $this->assertSame('strip', $aspirin->unit_middle);
        $this->assertSame(100, $aspirin->unitsPerTop());

        // Imatox injection: 5 ampoules, not an unknown pack.
        $imatox = $this->drug('Imatox Inj (Ondansetron)', '4mg/2ml');
        $this->assertSame(5, $imatox->top_holds);
        $this->assertSame('ampoule', $imatox->unit_base);
        $this->assertSame(5, $imatox->unitsPerTop());

        // Convenium Plus 300/12.5 is a different strength from the old 150/12.5.
        $this->drug('Convenium Plus', '300/12.5');
        $this->assertSame(0, Drug::where('name', 'Convenium Plus')->where('strength', '150/12.5')->count());

        // The safety-conflict row keeps both spellings visible.
        $gliclazide = $this->drug('Gliclazide (Glimepiride)', '4mg');
        $this->assertSame('Gliclazide / Glimepiride (conflict)', $gliclazide->generic_name);
    }

    public function test_rows_with_no_printed_count_are_sold_whole(): void
    {
        $this->import();

        foreach (['Enalapril', 'Nifedipine', 'Tinidazole (Amtiba)', 'Adol (Paracetamol)'] as $name) {
            $drug = Drug::where('name', $name)->firstOrFail();
            $levels = $drug->packingLevels();

            $this->assertCount(1, $levels, "{$name} must be one sold-whole rung until its count is confirmed.");
            $this->assertSame(1, $levels[0]['factor']);
            $this->assertSame($drug->unit_top, $levels[0]['name']);
        }

        // Aminophylline's invoice calls an ampoule a bottle; the container is
        // what is sold, and no ampoule count is invented.
        $aminophylline = $this->drug('Aminophylline', '200mg/5ml');
        $this->assertSame('bottle', $aminophylline->unit_top);
        $this->assertCount(1, $aminophylline->packingLevels());
    }

    public function test_an_existing_row_keeps_its_stock_sku_and_reorder_level(): void
    {
        $category = DrugCategory::firstOrCreate(['name' => 'Analgesics and Antipyretics']);
        $existing = Drug::create([
            'name' => 'Panadol Advance',
            'strength' => '500mg',
            'form' => 'tablet',
            'category_id' => $category->id,
            'unit_top' => 'pack',
            'top_holds' => 10,
            'unit_middle' => 'strip',
            'middle_holds' => 10,
            'unit_base' => 'tablet',
            'sell_middle' => false,
            'minimum_stock_level' => 42,
            'sku' => 'KEEP-ME-1',
        ]);

        $branch = Branch::create(['name' => 'Main Branch']);
        $supplier = Supplier::create(['name' => 'Medi Supply']);
        $batch = PurchaseBatch::create([
            'drug_id' => $existing->id,
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'batch_number' => 'OLD-1',
            'quantity_received' => 30,
            'quantity_remaining' => 30,
            'cost_per_unit' => 2,
            'selling_price_per_unit' => 3,
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_date' => now()->subMonth()->toDateString(),
        ]);

        $this->import();

        $existing->refresh();
        $this->assertSame('KEEP-ME-1', $existing->sku);
        $this->assertEquals(42, (float) $existing->minimum_stock_level);
        $this->assertSame(30, (int) $batch->fresh()->quantity_remaining);
        $this->assertSame(100, $existing->unitsPerTop());
        $this->assertFalse((bool) $existing->sell_middle, 'An unchanged packing chain keeps the units the pharmacy unticked.');
    }

    public function test_an_invoice_spelling_updates_the_existing_row_instead_of_duplicating_it(): void
    {
        $category = DrugCategory::firstOrCreate(['name' => 'Antivirals']);
        $old = Drug::create([
            'name' => 'Aciclovir',
            'strength' => '400mg',
            'form' => 'tablet',
            'category_id' => $category->id,
            'unit_top' => 'pack',
            'top_holds' => 1,
            'unit_base' => 'pack',
        ]);

        $this->import();

        $this->assertSame(1, Drug::where('name', 'Aciclovir')->count());
        $this->assertSame(0, Drug::where('name', 'Acyclovir')->count());

        $old->refresh();
        $this->assertSame('tablet', $old->unit_base);
        $this->assertSame(100, $old->unitsPerTop());
        $this->assertSame('Acyclovir', $old->generic_name);
    }

    public function test_sterile_gauze_stays_a_pack_sold_whole(): void
    {
        // The row that was moved over from Products keeps its SKU and its
        // reorder level of 100 packs.
        $category = DrugCategory::firstOrCreate(['name' => 'Other']);
        $gauze = Drug::create([
            'name' => 'Sterile Gauze Swabs 10 cm x 10 cm',
            'form' => 'piece',
            'category_id' => $category->id,
            'sku' => 'MS-GAU-10',
            'unit_top' => 'piece',
            'top_holds' => 1,
            'unit_base' => 'piece',
            'minimum_stock_level' => 100,
        ]);

        $this->import();

        $gauze->refresh();
        $this->assertSame('MS-GAU-10', $gauze->sku);
        $this->assertEquals(100, (float) $gauze->minimum_stock_level);
        $this->assertSame('pack', $gauze->unit_top);
        $this->assertSame('pack', $gauze->unit_base);
        $this->assertCount(1, $gauze->packingLevels());

        // The catalogue row itself was consumed by the SKU match.
        $this->assertSame(0, Drug::where('name', 'Gauze Swab')->count());
    }

    public function test_a_catalogue_row_can_be_bought_and_sold_by_its_own_rungs(): void
    {
        $this->import();

        $drug = $this->drug('Ciprofloxacin (Zindolin)', '500mg');
        $this->assertSame(100, $drug->unitsPerTop());

        $branch = Branch::create(['name' => 'Main Branch']);
        $supplier = Supplier::create(['name' => 'Medi Supply']);
        $owner = User::create([
            'name' => 'Owner', 'username' => 'owner', 'email' => 'owner@example.com',
            'password' => 'secret1234', 'role' => 'owner_admin', 'branch_id' => $branch->id,
        ]);

        // One pack of 100 tablets at 100.00 means 1.00 a tablet.
        $this->actingAs($owner)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'items' => [[
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'batch_number' => 'CAT-1',
                'quantity_received' => 1,
                'quantity_unit' => 'top',
                'cost_per_unit' => 60,
                'selling_price_per_unit' => 100,
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ])->assertSessionHasNoErrors();

        // Two strips are 20 tablets: 20.00, leaving 80.
        $this->actingAs($owner)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [[
                'drug_id' => 'drug:' . $drug->id,
                'quantity' => 2,
                'unit' => 'middle',
            ]],
            'payments' => [['payment_method' => 'cash', 'amount' => 20]],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(20.0, (float) Order::first()->total_amount, 0.001);
        $this->assertSame(80, (int) PurchaseBatch::first()->quantity_remaining);
    }
}
