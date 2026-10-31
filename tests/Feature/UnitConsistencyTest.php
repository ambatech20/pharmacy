<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\Order;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One packing description, read the same way everywhere.
 *
 * The drug list, the purchase screen and the till must all agree about what
 * the item is packed in, which of those rungs may be sold, and what a rung
 * costs. These tests pin that agreement and the arithmetic that carries a
 * price from the invoice to the shelf.
 */
class UnitConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Supplier $supplier;
    private DrugCategory $category;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->supplier = Supplier::create(['name' => 'Medi Supply']);
        $this->category = DrugCategory::create(['name' => 'Antibiotics']);

        $this->owner = User::create([
            'name' => 'Owner', 'username' => 'owner', 'email' => 'owner@example.com',
            'password' => 'secret1234', 'role' => 'owner_admin',
            'branch_id' => $this->branch->id,
        ]);
    }

    /** A box of 2 strips of 14 tablets. All three rungs are sellable. */
    private function boxed(): Drug
    {
        return Drug::create([
            'name' => 'Doxycycline',
            'category_id' => $this->category->id,
            'unit_top' => 'box',
            'unit_middle' => 'strip',
            'unit_base' => 'tablet',
            'top_holds' => 2,
            'middle_holds' => 14,
            'minimum_stock_level' => 1,
        ]);
    }

    /** A pack of 10 loose tablets: no strip rung exists. */
    private function loose(): Drug
    {
        return Drug::create([
            'name' => 'Amoxicillin',
            'category_id' => $this->category->id,
            'unit_top' => 'pack',
            'unit_middle' => null,
            'unit_base' => 'tablet',
            'top_holds' => 10,
            'minimum_stock_level' => 1,
        ]);
    }

    private function buy(Drug $drug, string $unit, int $qty, float $cost, float $sell)
    {
        return $this->actingAs($this->owner)->post(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'items' => [[
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'batch_number' => 'B-1',
                'quantity_received' => $qty,
                'quantity_unit' => $unit,
                'cost_per_unit' => $cost,
                'selling_price_per_unit' => $sell,
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ]);
    }

    public function test_the_drug_list_says_how_it_is_packed_and_what_may_be_sold(): void
    {
        $this->boxed();

        $this->actingAs($this->owner)
            ->get(route('drugs.index'))
            ->assertOk()
            ->assertSee('Box of 2 strips × 14 tablets')
            ->assertSee('Sells: Box, Strip, Tablet');
    }

    public function test_the_drug_list_states_stock_in_strips_not_loose_tablets(): void
    {
        $drug = $this->boxed();

        // 35 tablets is two and a half strips, never "2 strips 7 tablets".
        $this->assertSame('2.5 strips', $drug->describeStock(35));
        $this->assertSame('1 strip', $drug->describeStock(14));
        $this->assertSame('14 strips', $drug->describeStock(196));

        // Loose tablets have no strip, so the pack they came in is the unit.
        $this->assertSame('1.5 packs', $this->loose()->describeStock(15));

        // A container sold whole reads in its own unit.
        $bottle = Drug::create([
            'name' => 'Cough Syrup',
            'category_id' => $this->category->id,
            'unit_top' => 'bottle',
            'unit_base' => 'bottle',
            'top_holds' => 1,
            'minimum_stock_level' => 1,
        ]);
        $this->assertSame('3 bottles', $bottle->describeStock(3));

        // Test strips are already a shelf unit; their pack is not used.
        $strips = Drug::create([
            'name' => 'Glucose Test Strips',
            'category_id' => $this->category->id,
            'unit_top' => 'pack',
            'unit_base' => 'strip',
            'top_holds' => 50,
            'minimum_stock_level' => 1,
        ]);
        $this->assertSame('35 strips', $strips->describeStock(35));

        // And the list itself shows the strip count.
        PurchaseBatch::create([
            'drug_id' => $drug->id,
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'STK-95',
            'quantity_received' => 133,
            'quantity_remaining' => 133,
            'cost_per_unit' => 1,
            'selling_price_per_unit' => 2,
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->owner)
            ->get(route('drugs.index'))
            ->assertOk()
            ->assertSee('9.5 strips')
            ->assertDontSee('9 strips 5 tablets')
            ->assertSee('data-sidebar-fold', false)
            ->assertSee('data-sidebar-unfold', false);
    }

    public function test_the_purchase_screen_offers_every_rung_with_its_sellable_flag(): void
    {
        $drug = $this->boxed();

        $html = $this->actingAs($this->owner)
            ->get(route('purchases.create'))
            ->assertOk()
            ->getContent();

        // The same three rungs, biggest first, each with its size.
        $this->assertStringContainsString('"key":"top","name":"box","factor":28,"sellable":true', $html);
        $this->assertStringContainsString('"key":"middle","name":"strip","factor":14,"sellable":true', $html);
        $this->assertStringContainsString('"key":"base","name":"tablet","factor":1,"sellable":true', $html);
    }

    public function test_the_till_offers_the_same_rungs_the_purchase_screen_describes(): void
    {
        $drug = $this->boxed();

        $this->actingAs($this->owner)
            ->getJson(route('item.data', ['key' => 'drug:' . $drug->id]))
            ->assertOk()
            ->assertJsonPath('levels.0.name', 'box')
            ->assertJsonPath('levels.0.factor', 28)
            ->assertJsonPath('levels.1.name', 'strip')
            ->assertJsonPath('levels.1.factor', 14)
            ->assertJsonPath('levels.2.name', 'tablet')
            ->assertJsonPath('levels.2.factor', 1);
    }

    public function test_an_invoice_price_survives_the_round_trip_to_the_shelf(): void
    {
        $drug = $this->boxed();

        // 3 boxes at 120.00 -> 84 tablets at 4.2857 a tablet, not 4.29.
        $this->buy($drug, 'top', 3, 84.00, 120.00)
            ->assertSessionHasNoErrors();

        $batch = PurchaseBatch::firstOrFail();

        $this->assertSame(84, (int) $batch->quantity_received);
        $this->assertEqualsWithDelta(3.0, (float) $batch->cost_per_unit, 0.0001);
        $this->assertEqualsWithDelta(120 / 28, (float) $batch->selling_price_per_unit, 0.0001);

        // Rebuilt at the till, a box is 120.00 and a strip is 60.00 - the
        // invoice prices, not 120.12 / 60.06.
        $base = (float) $batch->selling_price_per_unit;
        $this->assertSame('120.00', number_format(round($base * 28, 2), 2, '.', ''));
        $this->assertSame('60.00', number_format(round($base * 14, 2), 2, '.', ''));
    }

    public function test_a_strip_sale_charges_the_strip_price_and_takes_a_strip_off_the_shelf(): void
    {
        $drug = $this->boxed();
        $this->buy($drug, 'top', 3, 84.00, 120.00)->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [[
                'drug_id' => 'drug:' . $drug->id,
                'quantity' => 2,
                'unit' => 'strip',
            ]],
            'payments' => [['payment_method' => 'cash', 'amount' => 120]],
        ])->assertSessionHasNoErrors();

        // Two strips = 28 tablets at 4.2857 = 120.00, and 56 tablets left.
        $this->assertEqualsWithDelta(120.0, (float) Order::first()->total_amount, 0.001);
        $this->assertSame(56, (int) PurchaseBatch::first()->quantity_remaining);
    }

    public function test_a_pack_of_loose_tablets_cannot_be_bought_as_a_strip(): void
    {
        $drug = $this->loose();

        // A cached form posting the old position word must not be read as
        // "tablets" - that would put stock in under the wrong name and price.
        $this->buy($drug, 'strip', 1, 10.00, 15.00)
            ->assertSessionHasErrors('error');

        $this->assertSame(0, PurchaseBatch::count());
    }

    public function test_a_pack_of_loose_tablets_can_still_be_bought_as_its_own_rungs(): void
    {
        $drug = $this->loose();

        $this->buy($drug, 'pack', 2, 20.00, 30.00)->assertSessionHasNoErrors();
        $this->buy($drug, 'tablet', 10, 2.00, 3.00)->assertSessionHasNoErrors();

        $this->assertSame(2, PurchaseBatch::count());
        $this->assertEqualsWithDelta(2.0, (float) PurchaseBatch::first()->cost_per_unit, 0.0001);
    }

    public function test_a_bottle_cannot_be_bought_by_the_tablet(): void
    {
        $drug = Drug::create([
            'name' => 'Klamoks',
            'category_id' => $this->category->id,
            'unit_top' => 'bottle',
            'unit_middle' => null,
            'unit_base' => 'bottle',
            'top_holds' => null,
            'minimum_stock_level' => 1,
        ]);

        $this->buy($drug, 'tablet', 1, 135.00, 160.00)
            ->assertSessionHasErrors('error');

        $this->assertSame(0, PurchaseBatch::count());
    }

    public function test_the_till_refuses_a_strip_when_the_drug_has_none(): void
    {
        $drug = $this->loose();
        $this->buy($drug, 'pack', 1, 10.00, 15.00)->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [[
                'drug_id' => 'drug:' . $drug->id,
                'quantity' => 1,
                'unit' => 'strip',
            ]],
            'payments' => [['payment_method' => 'cash', 'amount' => 1]],
        ])->assertSessionHasErrors('error');

        $this->assertSame(10, (int) PurchaseBatch::first()->quantity_remaining);
        $this->assertSame(0, Order::count());
    }
}
