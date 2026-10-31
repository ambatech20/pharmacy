<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may set a selling price.
 *
 * A price change is money: this screen backs the locked price box on the sale
 * screen, so anyone who can raise a price here can raise what every future
 * sale charges. Owner/Admin only, the same lever as a discount. Branch
 * managers keep the rest of Tier 2 - accounts, journal entries, reports - but
 * do not set prices.
 */
class BatchPricePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected PurchaseBatch $batch;
    protected User $owner;
    protected User $manager;
    protected User $clerk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Main Branch']);

        $this->batch = PurchaseBatch::create([
            'supplier_id' => Supplier::create(['name' => 'Medi Supply'])->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'B1',
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'cost_per_unit' => 10,
            'selling_price_per_unit' => 15,
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_date' => now()->toDateString(),
        ]);

        $make = fn (string $role, string $name) => User::create([
            'name' => $name,
            'username' => strtolower($name),
            'email' => strtolower($name) . '@example.com',
            'password' => 'secret1234',
            'role' => $role,
            'branch_id' => $this->branch->id,
        ]);

        $this->owner = $make('owner_admin', 'Owner');
        $this->manager = $make('branch_manager', 'Manager');
        $this->clerk = $make('pharmacist_clerk', 'Clerk');
    }

    public function test_the_owner_can_open_the_screen_and_set_a_price(): void
    {
        $this->actingAs($this->owner)->get(route('batch-prices.index'))->assertOk();

        $this->actingAs($this->owner)
            ->put(route('batch-prices.update', $this->batch), ['selling_price_per_unit' => 22])
            ->assertRedirect();

        $this->assertEquals(22.00, (float) $this->batch->fresh()->selling_price_per_unit);
    }

    public function test_a_branch_manager_cannot_open_or_change_a_price(): void
    {
        $this->actingAs($this->manager)->get(route('batch-prices.index'))->assertForbidden();

        // The route is the guard, so a hand-built form posting straight at the
        // update endpoint is refused too.
        $this->actingAs($this->manager)
            ->put(route('batch-prices.update', $this->batch), ['selling_price_per_unit' => 22])
            ->assertForbidden();

        $this->assertEquals(15.00, (float) $this->batch->fresh()->selling_price_per_unit, 'The price must not move.');
    }

    public function test_a_branch_manager_cannot_push_a_price_across_an_item(): void
    {
        $other = PurchaseBatch::create([
            'supplier_id' => $this->batch->supplier_id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'B2',
            'quantity_received' => 50,
            'quantity_remaining' => 50,
            'cost_per_unit' => 10,
            'selling_price_per_unit' => 15,
            'expiry_date' => now()->addYears(2)->toDateString(),
            'purchase_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->manager)
            ->put(route('batch-prices.apply', $this->batch))
            ->assertForbidden();

        $this->assertEquals(15.00, (float) $other->fresh()->selling_price_per_unit);
    }

    public function test_a_clerk_cannot_reach_it_either(): void
    {
        $this->actingAs($this->clerk)->get(route('batch-prices.index'))->assertForbidden();
    }

    public function test_the_sidebar_link_is_hidden_from_everyone_but_the_owner(): void
    {
        // Showing a link that ends in a 403 is a dead end, not a permission.
        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('batch-prices.index'));

        $this->actingAs($this->manager)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('batch-prices.index'));

        $this->actingAs($this->clerk)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('batch-prices.index'));
    }

    public function test_a_branch_manager_still_gets_the_rest_of_the_finance_screens(): void
    {
        // Locking prices must not lock out the accounting tools a branch
        // manager is expected to use.
        $this->actingAs($this->manager)->get(route('reports.financial'))->assertOk();
        $this->actingAs($this->manager)->get(route('accounts.index'))->assertOk();
    }
}
