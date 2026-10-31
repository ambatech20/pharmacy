<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Packing is described per drug, not assumed.
 *
 * The old model hard-coded tablet -> strip -> pack and offered all three for
 * every medicine, including a bottle of syrup. These tests pin the two things
 * that matter: the arithmetic between the rungs, and the way a quantity is
 * read back out ("2 packs 3 strips 4 capsules"), biggest unit first.
 */
class PackingLevelsTest extends TestCase
{
    use RefreshDatabase;

    private DrugCategory $category;
    private User $owner;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Main Branch']);

        $this->category = DrugCategory::create(['name' => 'Antibiotics']);

        $this->owner = User::create([
            'name' => 'Owner', 'username' => 'owner', 'email' => 'owner@example.com',
            'password' => 'secret1234', 'role' => 'owner_admin',
            'branch_id' => $this->branch->id,
        ]);
    }

    private function drug(array $attributes = []): Drug
    {
        return Drug::create(array_merge([
            'name' => 'Amoxicillin', 'strength' => '500mg',
            'category_id' => $this->category->id,
        ], $attributes));
    }

    /** The shape that started this: a pack of strips of capsules. */
    public function test_a_pack_of_strips_counts_down_to_the_capsule(): void
    {
        $drug = $this->drug([
            'unit_top' => 'pack', 'unit_middle' => 'strip', 'unit_base' => 'capsule',
            'top_holds' => 10, 'middle_holds' => 15,
            'minimum_stock_level' => 2,
        ]);

        // Deliberately not ten per strip: the count comes from the drug.
        $this->assertSame(150, $drug->unitsPerTop());
        $this->assertSame(150, $drug->baseUnitsPer('pack'));
        $this->assertSame(15, $drug->baseUnitsPer('strip'));
        $this->assertSame(1, $drug->baseUnitsPer('capsule'));

        // Selling works downward: a strip is a fifteenth of a pack.
        $this->assertSame(1.0, $drug->pricePerBase(15.0, 'strip'));
        $this->assertSame(150.0, $drug->pricePerUnit(1.0, 'pack'));
    }

    public function test_stock_is_read_from_the_biggest_unit_down(): void
    {
        $drug = $this->drug([
            'unit_top' => 'pack', 'unit_middle' => 'strip', 'unit_base' => 'capsule',
            'top_holds' => 10, 'middle_holds' => 15,
        ]);

        $this->assertSame('0 capsules', $drug->describeQuantity(0));
        $this->assertSame('4 capsules', $drug->describeQuantity(4));
        $this->assertSame('2 strips 4 capsules', $drug->describeQuantity(34));
        $this->assertSame('2 packs 3 strips 4 capsules', $drug->describeQuantity(349));
        $this->assertSame('24 packs', $drug->describeQuantity(3600));
    }

    /** A box of sachets has nothing in between the box and the sachet. */
    public function test_a_box_of_sachets_has_no_strip(): void
    {
        $drug = $this->drug([
            'name' => 'Oral Rehydration Salts',
            'unit_top' => 'box', 'unit_middle' => null, 'unit_base' => 'sachet',
            'top_holds' => 20, 'minimum_stock_level' => 2,
        ]);

        $levels = $drug->packingLevels();

        $this->assertCount(2, $levels);
        $this->assertSame(['top', 'base'], array_column($levels, 'key'));
        $this->assertSame(20, $drug->baseUnitsPer('box'));
        $this->assertSame(1, $drug->baseUnitsPer('sachet'));
        $this->assertSame('2 boxes 5 sachets', $drug->describeQuantity(45));
        $this->assertSame('Box of 20 sachets', $drug->packingSummary());
    }

    /** A bottle is never broken open: one rung, counted in bottles. */
    public function test_a_bottle_is_sold_whole(): void
    {
        $drug = $this->drug([
            'name' => 'Amoxicillin Suspension',
            'unit_top' => 'bottle', 'unit_middle' => null, 'unit_base' => 'bottle',
        ]);

        $this->assertCount(1, $drug->packingLevels());
        $this->assertSame(1, $drug->unitsPerTop());
        $this->assertSame('41 bottles', $drug->describeQuantity(41));
        $this->assertSame('1 bottle', $drug->describeQuantity(1));
        $this->assertSame('Bottle', $drug->packingSummary());
        $this->assertFalse($drug->sell_middle);
    }

    /** Minimum stock is in the upper unit, so 2 means two packs, not two tablets. */
    public function test_minimum_stock_is_measured_in_the_upper_unit(): void
    {
        $drug = $this->drug([
            'unit_top' => 'pack', 'unit_middle' => 'strip', 'unit_base' => 'tablet',
            'top_holds' => 10, 'middle_holds' => 10,
            'minimum_stock_level' => 2,
        ]);

        $this->assertSame(200.0, $drug->minimumInBaseUnits());
        $this->assertTrue($drug->isBelowMinimum(200));
        $this->assertFalse($drug->isBelowMinimum(201));
    }

    public function test_unticked_units_are_not_offered(): void
    {
        $drug = $this->drug([
            'name' => 'Azithromycin',
            'unit_top' => 'pack', 'unit_middle' => 'strip', 'unit_base' => 'tablet',
            'top_holds' => 10, 'middle_holds' => 3,
            'sell_middle' => false, 'sell_base' => true,
        ]);

        $this->assertSame(['top', 'base'], $drug->sellableUnitKeys());
    }

    /** The medicine form writes the sentence into the rungs. */
    public function test_the_form_stores_a_pack_of_strips_of_tablets(): void
    {
        $response = $this->actingAs($this->owner)->post(route('drugs.store'), [
            'name' => 'Doxycycline',
            'strength' => '100mg',
            'category_id' => $this->category->id,
            'unit_top' => 'box',
            'inner' => 'strip',
            'top_holds' => 4,
            'middle_holds' => 12,
            'unit_base' => 'tablet',
            'sell_middle' => '1',
            'sell_base' => '1',
            'minimum_stock_level' => 2,
        ]);

        $response->assertRedirect(route('drugs.index'));

        $drug = Drug::where('name', 'Doxycycline')->firstOrFail();

        $this->assertSame('box', $drug->unit_top);
        $this->assertSame('strip', $drug->unit_middle);
        $this->assertSame('tablet', $drug->unit_base);
        $this->assertSame(4, $drug->top_holds);
        $this->assertSame(12, $drug->middle_holds);
        $this->assertSame(48, (int) $drug->units_per_top);
        $this->assertSame('2.00', $drug->minimum_stock_level);

        // The old columns follow along, so unconverted screens keep working.
        $this->assertSame('tablet', $drug->unit_of_measure);
        $this->assertSame(12, (int) $drug->units_per_strip);
        $this->assertSame(4, (int) $drug->strips_per_pack);
    }

    public function test_the_form_stores_a_bottle_as_one_rung(): void
    {
        $this->actingAs($this->owner)->post(route('drugs.store'), [
            'name' => 'Cough Syrup',
            'category_id' => $this->category->id,
            'unit_top' => 'bottle',
            'inner' => 'none',
            'minimum_stock_level' => 5,
        ])->assertRedirect(route('drugs.index'));

        $drug = Drug::where('name', 'Cough Syrup')->firstOrFail();

        $this->assertSame('bottle', $drug->unit_top);
        $this->assertNull($drug->unit_middle);
        $this->assertSame('bottle', $drug->unit_base);
        $this->assertNull($drug->top_holds);
        $this->assertSame(1, (int) $drug->units_per_top);
        $this->assertSame('41 bottles', $drug->describeQuantity(41));
    }

    /**
     * A tin or a tube is still a container, not a synonym for "sold whole".
     * The invoices describe a tin of 30 tablets and a tube of 20 tablets, and
     * the form must write those counts rather than quietly collapsing them.
     */
    public function test_the_form_keeps_countable_contents_in_a_tin_or_tube(): void
    {
        foreach ([
            ['Tin of Folic Acid', 'tin', 30],
            ['Tube of Calci-Denk', 'tube', 20],
        ] as [$name, $container, $count]) {
            $this->actingAs($this->owner)->post(route('drugs.store'), [
                'name' => $name,
                'category_id' => $this->category->id,
                'unit_top' => $container,
                'inner' => 'loose',
                'top_holds' => $count,
                'unit_base' => 'tablet',
                'sell_base' => '1',
                'minimum_stock_level' => 1,
            ])->assertRedirect(route('drugs.index'));

            $drug = Drug::where('name', $name)->firstOrFail();

            $this->assertSame($container, $drug->unit_top, $name);
            $this->assertNull($drug->unit_middle, $name);
            $this->assertSame('tablet', $drug->unit_base, $name);
            $this->assertSame($count, $drug->top_holds, $name);
            $this->assertSame($count, $drug->unitsPerTop(), $name);
            $this->assertSame(
                [
                    ['key' => 'top', 'name' => $container, 'factor' => $count, 'sellable' => true],
                    ['key' => 'base', 'name' => 'tablet', 'factor' => 1, 'sellable' => true],
                ],
                $drug->packingLevels(),
                $name
            );
        }
    }

    /** Editing an imported tin must not turn 30 tablets into one tin. */
    public function test_editing_a_tin_keeps_its_tablet_rung(): void
    {
        $drug = $this->drug([
            'name' => 'Folic Acid',
            'unit_top' => 'tin',
            'unit_middle' => null,
            'unit_base' => 'tablet',
            'top_holds' => 30,
            'minimum_stock_level' => 1,
        ]);

        $this->assertSame('loose', $drug->packingFormState()['inner']);

        $this->actingAs($this->owner)->put(route('drugs.update', $drug), [
            'name' => 'Folic Acid',
            'category_id' => $this->category->id,
            'unit_top' => 'tin',
            'inner' => 'loose',
            'top_holds' => 30,
            'unit_base' => 'tablet',
            'sell_base' => '1',
            'minimum_stock_level' => 1,
        ])->assertRedirect(route('drugs.index'));

        $drug->refresh();

        $this->assertSame('tin', $drug->unit_top);
        $this->assertSame('tablet', $drug->unit_base);
        $this->assertSame(30, $drug->top_holds);
        $this->assertSame(30, $drug->unitsPerTop());
        $this->assertSame('Tin of 30 tablets', $drug->packingSummary());
    }

    /** A pack of vials says "vials", not "loose items". */
    public function test_the_form_stores_a_pack_of_vials_as_vials(): void
    {
        $this->actingAs($this->owner)->post(route('drugs.store'), [
            'name' => 'Cefe-Denk',
            'category_id' => $this->category->id,
            'unit_top' => 'pack',
            'inner' => 'vial',
            'top_holds' => 20,
            'sell_base' => '1',
            'minimum_stock_level' => 1,
        ])->assertRedirect(route('drugs.index'));

        $drug = Drug::where('name', 'Cefe-Denk')->firstOrFail();

        $this->assertSame('pack', $drug->unit_top);
        $this->assertSame('vial', $drug->unit_base);
        $this->assertSame(20, $drug->unitsPerTop());
        $this->assertSame(
            [
                ['key' => 'top', 'name' => 'pack', 'factor' => 20, 'sellable' => true],
                ['key' => 'base', 'name' => 'vial', 'factor' => 1, 'sellable' => true],
            ],
            $drug->packingLevels()
        );
        $this->assertSame('vial', $drug->packingFormState()['inner']);
        $this->assertSame('Pack of 20 vials', $drug->packingSummary());
    }

    /** Irregular plurals are read on the shelf, not in a grammar book. */
    public function test_irregular_units_are_pluralised_correctly(): void
    {
        $suppository = $this->drug([
            'name' => 'Suppository Pack',
            'unit_top' => 'pack',
            'unit_base' => 'suppository',
            'top_holds' => 10,
        ]);

        $this->assertSame('5 suppositories', $suppository->describeQuantity(5));
        $this->assertSame('Pack of 10 suppositories', $suppository->packingSummary());

        $pessary = $this->drug([
            'name' => 'Pessary Pack',
            'unit_top' => 'pack',
            'unit_base' => 'pessary',
            'top_holds' => 6,
        ]);

        $this->assertSame('3 pessaries', $pessary->describeQuantity(3));
        $this->assertSame('Pack of 6 pessaries', $pessary->packingSummary());
    }

    /**
     * The form offers "Bottle containing Bottles" if an old value is still
     * selected. That is not a packing chain, and must not be turned into one.
     */
    public function test_the_form_refuses_a_container_that_contains_itself(): void
    {
        foreach (['bottle', 'vial', 'tin', 'tube'] as $container) {
            $this->actingAs($this->owner)->post(route('drugs.store'), [
                'name' => 'Impossible ' . $container,
                'category_id' => $this->category->id,
                'unit_top' => $container,
                'inner' => $container,
                'top_holds' => 10,
                'unit_base' => 'tablet',
                'minimum_stock_level' => 1,
            ])->assertSessionHasErrors('inner');

            $this->assertDatabaseMissing('drugs', ['name' => 'Impossible ' . $container]);
        }
    }

    /**
     * Imported data can contain the same word on two rungs. The shelf reading
     * must stay unique and the total conversion must not be lost.
     */
    public function test_a_duplicate_middle_unit_is_collapsed_without_losing_its_factor(): void
    {
        $drug = $this->drug([
            'name' => 'Duplicate Strip',
            'unit_top' => 'box',
            'unit_middle' => 'strip',
            'unit_base' => 'strip',
            'top_holds' => 4,
            'middle_holds' => 12,
        ]);

        $this->assertNull($drug->unit_middle);
        $this->assertSame(48, $drug->top_holds);
        $this->assertSame(
            [
                ['key' => 'top', 'name' => 'box', 'factor' => 48, 'sellable' => true],
                ['key' => 'base', 'name' => 'strip', 'factor' => 1, 'sellable' => true],
            ],
            $drug->packingLevels()
        );
    }

    /** An imported container holding itself is one countable unit, not thirty. */
    public function test_a_container_holding_itself_collapses_to_one_rung(): void
    {
        $drug = $this->drug([
            'name' => 'Self Tin',
            'unit_top' => 'tin',
            'unit_middle' => null,
            'unit_base' => 'tin',
            'top_holds' => 30,
        ]);

        $this->assertNull($drug->top_holds);
        $this->assertSame(1, $drug->unitsPerTop());
        $this->assertSame(['tin'], array_column($drug->packingLevels(), 'name'));
        $this->assertSame('none', $drug->packingFormState()['inner']);
        $this->assertSame('30 tins', $drug->describeQuantity(30));
    }

    /**
     * If an import loses the "one pack holds X" count, showing a 1:1 pack is
     * worse than showing the unit stock is actually stored in. The form then
     * asks for the missing count before it can be saved.
     */
    public function test_a_count_without_a_container_falls_back_to_the_base_unit(): void
    {
        $drug = $this->drug([
            'name' => 'Missing Count',
            'unit_top' => 'pack',
            'unit_middle' => null,
            'unit_base' => 'tablet',
            'top_holds' => null,
        ]);

        $this->assertSame(1, $drug->unitsPerTop());
        $this->assertSame(
            [['key' => 'base', 'name' => 'tablet', 'factor' => 1, 'sellable' => true]],
            $drug->packingLevels()
        );
        $this->assertSame('', $drug->packingFormState()['top_holds']);
    }

    public function test_the_form_refuses_a_pack_that_holds_nothing_specified(): void
    {
        $this->actingAs($this->owner)->post(route('drugs.store'), [
            'name' => 'Mystery Pack',
            'category_id' => $this->category->id,
            'unit_top' => 'pack',
            'inner' => 'strip',
            'middle_holds' => 10,
        ])->assertSessionHasErrors('top_holds');

        $this->assertDatabaseMissing('drugs', ['name' => 'Mystery Pack']);
    }

    /**
     * Drugs created before the packing columns existed still behave exactly as
     * they did: 10 tablets to a strip, 10 strips to a pack.
     */
    public function test_a_drug_with_only_the_old_columns_still_works(): void
    {
        $drug = $this->drug([
            'unit_of_measure' => 'tablet',
            'units_per_strip' => 10,
            'strips_per_pack' => 10,
        ]);

        $this->assertSame(100, $drug->unitsPerTop());
        $this->assertSame('1 pack 5 tablets', $drug->describeQuantity(105));
        $this->assertSame('pack', $drug->packingFormState()['unit_top']);
        $this->assertSame('strip', $drug->packingFormState()['inner']);
    }

    /** A strip with no count would divide every price by one without saying so. */
    public function test_the_form_refuses_a_strip_that_holds_nothing(): void
    {
        $this->actingAs($this->owner)->post(route('drugs.store'), [
            'name' => 'Half-Described',
            'category_id' => $this->category->id,
            'unit_top' => 'pack',
            'inner' => 'strip',
            'top_holds' => 10,
        ])->assertSessionHasErrors('middle_holds');

        $this->assertDatabaseMissing('drugs', ['name' => 'Half-Described']);
    }

    /**
     * A drug whose top unit is outside the usual six must survive a round trip
     * through the form. Otherwise the first option (Pack) would silently win.
     */
    public function test_editing_keeps_a_unit_the_dropdown_does_not_normally_offer(): void
    {
        $drug = $this->drug([
            'name' => 'Loose Strip Medicine',
            'unit_top' => 'strip', 'unit_middle' => null, 'unit_base' => 'tablet',
            'top_holds' => 12,
        ]);

        // The edit screen offers the drug's own unit rather than defaulting.
        $this->actingAs($this->owner)
            ->get(route('drugs.edit', $drug))
            ->assertOk()
            ->assertSee('<option value="strip" selected>Strip</option>', false);

        // "Strip" is genuinely this drug's biggest unit, so that is what the
        // old screens' Strip option must mean.
        $this->assertSame(12, $drug->baseUnitsPer('strip'));

        $this->actingAs($this->owner)->put(route('drugs.update', $drug), [
            'name' => 'Loose Strip Medicine',
            'category_id' => $this->category->id,
            'unit_top' => 'strip',
            'inner' => 'loose',
            'top_holds' => 12,
            'unit_base' => 'tablet',
            'minimum_stock_level' => 1,
        ])->assertRedirect(route('drugs.index'));

        $drug->refresh();

        $this->assertSame('strip', $drug->unit_top);
        $this->assertSame(12, $drug->unitsPerTop());
        $this->assertSame('1 strip', $drug->describeQuantity(12));
    }

    /** The old columns could express "1 pack = 1 tablet"; that is a pack count. */
    public function test_a_legacy_one_to_one_drug_collapses_to_a_single_rung(): void
    {
        $drug = $this->drug([
            'name' => 'Odd Legacy',
            'unit_of_measure' => 'tablet',
            'units_per_strip' => 1,
            'strips_per_pack' => 1,
        ]);

        $this->assertCount(1, $drug->packingLevels());
        $this->assertSame('pack', $drug->topUnitLabel());
        $this->assertSame('7 packs', $drug->describeQuantity(7));
    }

    public function test_packing_options_read_like_the_shelf(): void
    {
        $drug = $this->drug([
            'unit_top' => 'pack', 'unit_middle' => 'strip', 'unit_base' => 'capsule',
            'top_holds' => 10, 'middle_holds' => 15,
        ]);

        $this->assertSame([
            'top' => 'Pack (150 capsules)',
            'middle' => 'Strip (15 capsules)',
            'base' => 'Capsule',
        ], $drug->packingOptions());
    }

    /**
     * The screens that are not converted yet send pack / strip / tablet. Those
     * are positions - biggest, middle, smallest - not names, or a box drug
     * would be bought as one tablet.
     */
    public function test_the_old_screen_words_mean_biggest_middle_smallest(): void
    {
        $drug = $this->drug([
            'unit_top' => 'box', 'unit_middle' => 'strip', 'unit_base' => 'tablet',
            'top_holds' => 4, 'middle_holds' => 12,
        ]);

        $this->assertSame(48, $drug->baseUnitsPer('pack'));
        $this->assertSame(12, $drug->baseUnitsPer('strip'));
        $this->assertSame(1, $drug->baseUnitsPer('tablet'));

        // The real names and the keys work too.
        $this->assertSame(48, $drug->baseUnitsPer('box'));
        $this->assertSame(48, $drug->baseUnitsPer('top'));
        $this->assertSame(12, $drug->baseUnitsPer('middle'));
        $this->assertSame(1, $drug->baseUnitsPer('base'));
    }

    /** A drug with one rung answers "one of it" to every old word, as before. */
    public function test_old_screen_words_stay_harmless_on_a_bottle(): void
    {
        $drug = $this->drug([
            'name' => 'Cough Syrup',
            'unit_top' => 'bottle', 'unit_middle' => null, 'unit_base' => 'bottle',
        ]);

        $this->assertSame(1, $drug->baseUnitsPer('pack'));
        $this->assertSame(1, $drug->baseUnitsPer('strip'));
        $this->assertSame(1, $drug->baseUnitsPer('tablet'));
        $this->assertSame(1, $drug->baseUnitsPer('bottle'));
    }

    /**
     * The whole chain, on a packing where nothing is ten: buy two boxes, sell
     * three strips, and check the shelf in base units afterwards.
     */
    public function test_a_box_of_four_strips_of_twelve_buys_and_sells_correctly(): void
    {
        $this->seed(\Database\Seeders\AccountSeeder::class);

        $supplier = \App\Models\Supplier::create(['name' => 'Medi Supply']);

        $drug = $this->drug([
            'name' => 'Doxycycline',
            'unit_top' => 'box', 'unit_middle' => 'strip', 'unit_base' => 'tablet',
            'top_holds' => 4, 'middle_holds' => 12,
            'minimum_stock_level' => 1,
        ]);

        // 240 birr a box = 60 a strip = 5 a tablet; sold at 6 a tablet.
        // The purchase screen still says "Pack" for the biggest unit.
        $this->actingAs($this->owner)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'items' => [[
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'batch_number' => 'BOX-1',
                'quantity_received' => 2,
                'quantity_unit' => 'pack',
                'cost_per_unit' => 240,
                'selling_price_per_unit' => 288,
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ])->assertSessionHasNoErrors();

        $batch = \App\Models\PurchaseBatch::where('drug_id', $drug->id)->firstOrFail();

        // Two boxes of 4 x 12 = 96 tablets, whatever the box looked like.
        $this->assertSame(96, (int) $batch->quantity_received);
        $this->assertEqualsWithDelta(5.0, (float) $batch->cost_per_unit, 0.001);
        $this->assertEqualsWithDelta(6.0, (float) $batch->selling_price_per_unit, 0.001);

        $this->actingAs($this->owner)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [[
                'drug_id' => 'drug:' . $drug->id,
                'quantity' => 3,
                'unit' => 'strip',
            ]],
            'payments' => [['payment_method' => 'cash', 'amount' => 216]],
        ])->assertSessionHasNoErrors();

        $batch->refresh();

        // 96 - (3 x 12) = 60 tablets = one box and one strip.
        $this->assertSame(60, (int) $batch->quantity_remaining);
        $this->assertSame('1 box 1 strip', $drug->describeQuantity(60));

        $this->assertEqualsWithDelta(
            216.0,
            (float) \App\Models\Order::query()->latest('id')->value('total_amount'),
            0.01
        );
    }
}
