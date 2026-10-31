<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\IssuedItem;
use App\Models\Order;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The locked sale price and owner-only discounts.
 *
 * The price charged is derived server-side from the newest batch that still
 * has stock, so the read-only box on the sale screen cannot be used to change
 * what a customer pays. Discounts are an Owner/Admin lever and are written to
 * the activity log.
 */
class SalePricingTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected Supplier $supplier;
    protected Drug $drug;
    protected User $owner;
    protected User $clerk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->supplier = Supplier::create(['name' => 'Medi Supply']);
        $this->drug = Drug::create([
            'name' => 'Paracetamol',
            'strength' => '500mg',
            'unit_of_measure' => 'tablet',
            'units_per_strip' => 10,
            'strips_per_pack' => 20,
            'category_id' => DrugCategory::create(['name' => 'Pain relief'])->id,
        ]);

        $make = fn (array $extra) => User::create($extra + [
            'password' => 'secret1234',
            'branch_id' => $this->branch->id,
        ]);
        $this->owner = $make(['name' => 'Owner', 'username' => 'owner', 'email' => 'owner@example.com', 'role' => 'owner_admin']);
        $this->clerk = $make(['name' => 'Clerk', 'username' => 'clerk', 'email' => 'clerk@example.com', 'role' => 'pharmacist_clerk']);
    }

    protected function batch(string $batchNumber, string $price, int $remaining, ?string $purchaseDate = null): PurchaseBatch
    {
        return PurchaseBatch::create([
            'drug_id' => $this->drug->id,
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'batch_number' => $batchNumber,
            'quantity_received' => $remaining,
            'quantity_remaining' => $remaining,
            'cost_per_unit' => 10,
            'selling_price_per_unit' => $price,
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_date' => $purchaseDate ?? now()->toDateString(),
        ]);
    }

    /**
     * Sell $qty, optionally with a discount and a forged price. $paid must
     * equal the sale total: the app refuses a payment that does not add up.
     */
    protected function sell(User $user, int $qty, float $paid, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [array_merge([
                'drug_id' => 'drug:' . $this->drug->id,
                'quantity' => $qty,
                'unit' => 'tablet',
            ], $extra)],
            'payments' => [['payment_method' => 'cash', 'amount' => $paid]],
        ]);
    }

    public function test_price_comes_from_the_newest_batch_not_the_one_dispensed(): void
    {
        // FEFO takes the OLD batch out first, but the ticket is priced from the
        // NEWEST batch so one item never shows two prices on one receipt.
        $this->batch('OLD', 8.00, 50, now()->subMonths(2)->toDateString());
        $this->batch('NEW', 9.00, 50, now()->toDateString());

        $this->sell($this->clerk, 2, 18.00)->assertRedirect();

        $issued = IssuedItem::first();
        $this->assertEquals(9.00, (float) $issued->selling_price_per_unit);
        // ...but the batch that actually left the shelf is the old one.
        $this->assertSame('OLD', $issued->purchaseBatch->batch_number);
        $this->assertEquals(18.00, (float) Order::first()->total_amount);
    }

    public function test_a_forged_price_in_the_post_is_ignored(): void
    {
        $this->batch('B', 9.00, 50);

        // The box is readonly, but a crafted request can still carry anything.
        $this->sell($this->clerk, 1, 9.00, ['selling_price' => 0.01])->assertRedirect();

        $this->assertEquals(9.00, (float) IssuedItem::first()->selling_price_per_unit);
        $this->assertEquals(9.00, (float) Order::first()->total_amount);
    }

    public function test_owner_may_discount_and_it_is_logged_with_both_prices(): void
    {
        $this->batch('B', 20.00, 50);

        $this->sell($this->owner, 2, 30.00, ['discount' => 5])->assertRedirect();

        $issued = IssuedItem::first();
        $this->assertEquals(15.00, (float) $issued->selling_price_per_unit);
        $this->assertEquals(5.00, (float) $issued->unit_discount);
        $this->assertEquals(30.00, (float) Order::first()->total_amount);

        $log = ActivityLog::where('action', 'discount')->first();
        $this->assertNotNull($log, 'A discount must leave an audit entry.');
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertEquals(20.00, (float) $log->changes['shelf_price_per_base_unit']);
        $this->assertEquals(15.00, (float) $log->changes['charged_price_per_base_unit']);
        $this->assertEquals(5.00, (float) $log->changes['discount_per_base_unit']);
    }

    public function test_a_clerk_cannot_discount(): void
    {
        $this->batch('B', 20.00, 50);

        $this->sell($this->clerk, 1, 15.00, ['discount' => 5])->assertSessionHasErrors();

        $this->assertSame(0, IssuedItem::count());
        $this->assertSame(0, ActivityLog::where('action', 'discount')->count());
        $this->assertEquals(50, (int) PurchaseBatch::first()->quantity_remaining, 'The sale rolled back.');
    }

    public function test_a_branch_manager_cannot_discount_either(): void
    {
        // A branch manager sees the Finance screens, so it would be easy to
        // assume pricing is theirs too. It is not: discounts are the owner's.
        $manager = User::create([
            'name' => 'Manager',
            'username' => 'manager',
            'email' => 'manager@example.com',
            'password' => 'secret1234',
            'role' => 'branch_manager',
            'branch_id' => $this->branch->id,
        ]);

        $this->batch('B', 20.00, 50);

        $this->sell($manager, 1, 15.00, ['discount' => 5])->assertSessionHasErrors();

        $this->assertSame(0, IssuedItem::count());
        $this->assertEquals(50, (int) PurchaseBatch::first()->quantity_remaining);
    }

    public function test_the_discount_box_is_not_rendered_for_a_clerk(): void
    {
        $this->batch('B', 20.00, 50);

        // Hiding the field is convenience; the server refusing it is the rule.
        $this->actingAs($this->clerk)->get(route('dispense.create'))
            ->assertOk()
            ->assertDontSee('items[0][discount]');

        $this->actingAs($this->owner)->get(route('dispense.create'))
            ->assertOk()
            ->assertSee('items[0][discount]');
    }

    public function test_discount_is_per_selected_unit_and_scales_down_to_base(): void
    {
        $this->batch('B', 100.00, 500); // per BASE unit; a strip of 10 = 1000

        // 200 off per strip is 20 off per base unit.
        $this->sell($this->owner, 2, 1600.00, ['unit' => 'strip', 'discount' => 200])->assertRedirect();

        $issued = IssuedItem::first();
        $this->assertEquals(20, (int) $issued->quantity_issued, '2 strips = 20 base units.');
        $this->assertEquals(80.00, (float) $issued->selling_price_per_unit);
        $this->assertEquals(20.00, (float) $issued->unit_discount);
    }

    public function test_a_discount_equal_to_the_price_is_refused(): void
    {
        $this->batch('B', 10.00, 50);

        $this->sell($this->owner, 1, 0.01, ['discount' => 10])->assertSessionHasErrors();

        $this->assertSame(0, IssuedItem::count());
    }

    public function test_selling_a_used_up_newest_batch_falls_back_to_the_previous_price(): void
    {
        $this->batch('OLD', 8.00, 50, now()->subMonths(2)->toDateString());
        $this->batch('NEW', 9.00, 0, now()->toDateString()); // bought, but sold out

        $this->sell($this->clerk, 1, 8.00)->assertRedirect();

        $this->assertEquals(8.00, (float) IssuedItem::first()->selling_price_per_unit);
        $this->assertSame('OLD', IssuedItem::first()->purchaseBatch->batch_number);
    }

    public function test_the_price_screen_updates_what_sales_charge(): void
    {
        $batch = $this->batch('B', 9.00, 50);

        $this->actingAs($this->owner)
            ->put(route('batch-prices.update', $batch), ['selling_price_per_unit' => 11.50])
            ->assertRedirect();

        $this->sell($this->clerk, 1, 11.50)->assertRedirect();
        $this->assertEquals(11.50, (float) IssuedItem::first()->selling_price_per_unit);
    }

    public function test_applying_to_item_updates_every_open_batch(): void
    {
        $a = $this->batch('A', 8.00, 50, now()->subMonths(2)->toDateString());
        $b = $this->batch('B', 9.00, 50, now()->toDateString());
        $spent = $this->batch('C', 5.00, 0, now()->subYear()->toDateString());

        $this->actingAs($this->owner)->put(route('batch-prices.apply', $b))->assertRedirect();

        $this->assertEquals(9.00, (float) $a->fresh()->selling_price_per_unit);
        $this->assertEquals(9.00, (float) $b->fresh()->selling_price_per_unit);
        $this->assertEquals(5.00, (float) $spent->fresh()->selling_price_per_unit, 'A used-up batch is left alone.');
    }

    public function test_a_clerk_cannot_open_or_change_batch_prices(): void
    {
        $batch = $this->batch('B', 9.00, 50);

        $this->actingAs($this->clerk)->get(route('batch-prices.index'))->assertForbidden();
        $this->actingAs($this->clerk)
            ->put(route('batch-prices.update', $batch), ['selling_price_per_unit' => 1])
            ->assertForbidden();
        $this->assertEquals(9.00, (float) $batch->fresh()->selling_price_per_unit);
    }
}
