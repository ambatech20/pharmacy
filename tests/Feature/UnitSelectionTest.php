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
 * The sale and purchase screens now take their unit list from the drug, not
 * from the old tablet/strip/pack list. These tests pin the three promises that
 * makes:
 *
 *   - the till only offers the rungs ticked on the drug;
 *   - the server refuses an unticked one even if a stale tab sends it;
 *   - a purchase line entered as "2 boxes" is stored as base units at a base
 *     cost, and the price of a sold strip is derived from the base price.
 */
class UnitSelectionTest extends TestCase
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

    /** 4 strips x 12 tablets = 48 tablets in a box. */
    private function drug(array $overrides = []): Drug
    {
        return Drug::create(array_merge([
            'name' => 'Doxycycline',
            'category_id' => $this->category->id,
            'unit_top' => 'box',
            'unit_middle' => 'strip',
            'unit_base' => 'tablet',
            'top_holds' => 4,
            'middle_holds' => 12,
            'minimum_stock_level' => 1,
        ], $overrides));
    }

    /** Two boxes on the shelf: 5.00 cost and 6.00 shelf price per tablet. */
    private function stock(Drug $drug, int $boxes = 2): PurchaseBatch
    {
        return PurchaseBatch::create([
            'drug_id' => $drug->id,
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'BOX-1',
            'quantity_received' => $boxes * 48,
            'quantity_remaining' => $boxes * 48,
            'cost_per_unit' => 5,
            'selling_price_per_unit' => 6,
            'expiry_date' => now()->addYear(),
            'purchase_date' => now(),
        ]);
    }

    public function test_the_till_is_offered_only_the_units_ticked_on_the_drug(): void
    {
        $drug = $this->drug(['sell_middle' => false]);

        $row = $this->actingAs($this->owner)
            ->getJson(route('search.drug', ['q' => 'Doxy']))
            ->assertOk()
            ->json('results.0');

        // The strip is unticked, so it must not reach the browser at all.
        $this->assertSame([
            ['key' => 'top', 'name' => 'box', 'factor' => 48],
            ['key' => 'base', 'name' => 'tablet', 'factor' => 1],
        ], $row['levels']);
    }

    public function test_the_server_refuses_a_unit_the_drug_does_not_sell(): void
    {
        $drug = $this->drug(['sell_middle' => false]);
        $batch = $this->stock($drug);

        $this->actingAs($this->owner)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [[
                'drug_id' => 'drug:' . $drug->id,
                'quantity' => 1,
                'unit' => 'strip',
            ]],
            'payments' => [['payment_method' => 'cash', 'amount' => 72]],
        ])->assertSessionHasErrors('error');

        $this->assertSame(96, (int) $batch->fresh()->quantity_remaining);
        $this->assertSame(0, Order::count());
    }

    public function test_selling_a_strip_charges_the_strip_price_and_deducts_strip_quantity(): void
    {
        $drug = $this->drug();
        $batch = $this->stock($drug);

        // Two strips = 24 tablets at 6.00 = 144.00.
        $this->actingAs($this->owner)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [[
                'drug_id' => 'drug:' . $drug->id,
                'quantity' => 2,
                'unit' => 'middle',
            ]],
            'payments' => [['payment_method' => 'cash', 'amount' => 144]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(72, (int) $batch->fresh()->quantity_remaining);
        $this->assertEqualsWithDelta(144.0, (float) Order::first()->total_amount, 0.001);
    }

    public function test_buying_in_boxes_stores_base_units_and_a_base_cost(): void
    {
        $drug = $this->drug();

        $this->actingAs($this->owner)->post(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'items' => [[
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'batch_number' => 'BOX-9',
                'quantity_received' => 3,
                'quantity_unit' => 'top',
                'cost_per_unit' => 240,
                'selling_price_per_unit' => 288,
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ])->assertSessionHasNoErrors();

        $batch = PurchaseBatch::where('drug_id', $drug->id)->firstOrFail();

        // 3 boxes of 48 tablets, at 240 / 48 = 5.00 a tablet.
        $this->assertSame(144, (int) $batch->quantity_received);
        $this->assertEqualsWithDelta(5.0, (float) $batch->cost_per_unit, 0.001);
        $this->assertEqualsWithDelta(6.0, (float) $batch->selling_price_per_unit, 0.001);
    }

    /**
     * The screens must take their unit list from the drug. A hardcoded list
     * here is exactly what this change exists to remove, and it is easy for a
     * later edit to put one back.
     */
    public function test_the_sale_screen_does_not_ship_a_fixed_unit_list(): void
    {
        $this->drug();

        $html = $this->actingAs($this->owner)
            ->get(route('dispense.create'))
            ->assertOk()
            ->content();

        $this->assertStringNotContainsString('<option value="strip">Strip</option>', $html);
        $this->assertStringNotContainsString('<option value="tablet">', $html);
        $this->assertStringContainsString('unitOptionsHtml', $html);
    }

    public function test_the_purchase_screen_carries_each_drugs_own_rungs(): void
    {
        $this->drug();

        $html = $this->actingAs($this->owner)
            ->get(route('purchases.create'))
            ->assertOk()
            ->content();

        // The map the browser fills the unit select from: this drug is a box
        // of 4 strips of 12 tablets.
        $this->assertStringContainsString('"name":"box"', $html);
        $this->assertStringContainsString('"name":"strip"', $html);
        $this->assertStringContainsString('"factor":48', $html);
        $this->assertStringContainsString('purchase-unit', $html);
    }

    public function test_a_purchase_line_cannot_buy_a_rung_the_drug_does_not_have(): void
    {
        // A bottle is one rung: there is no base unit to buy.
        $drug = $this->drug([
            'name' => 'Klamoks',
            'unit_top' => 'bottle',
            'unit_middle' => null,
            'unit_base' => 'bottle',
            'top_holds' => null,
            'middle_holds' => null,
        ]);

        $this->actingAs($this->owner)->post(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'items' => [[
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'batch_number' => 'BOT-1',
                'quantity_received' => 1,
                'quantity_unit' => 'base',
                'cost_per_unit' => 135,
                'selling_price_per_unit' => 160,
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ])->assertSessionHasErrors('error');

        $this->assertSame(0, PurchaseBatch::count());
    }
}
