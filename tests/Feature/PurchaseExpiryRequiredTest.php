<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Expiry date is mandatory on every purchase line.
 *
 * FEFO dispensing, the expiring-soon alerts and the "batch has no expiry on
 * file" edge case all assume a date exists, so a line may not be saved without
 * one. Lines for stock that genuinely never expires use a far-future date.
 */
class PurchaseExpiryRequiredTest extends TestCase
{
    use RefreshDatabase;

    protected User $clerk;
    protected Branch $branch;
    protected Supplier $supplier;
    protected Drug $drug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->supplier = Supplier::create(['name' => 'Medi Supply']);
        $this->drug = Drug::create([
            'name' => 'Paracetamol',
            'category_id' => DrugCategory::create(['name' => 'Pain relief'])->id,
            'unit_top' => 'pack',
            'top_holds' => 10,
            'unit_base' => 'tablet',
        ]);
        $this->clerk = User::create([
            'name' => 'Clerk',
            'username' => 'clerk',
            'email' => 'clerk@example.com',
            'password' => 'secret1234',
            'role' => 'pharmacist_clerk',
            'branch_id' => $this->branch->id,
        ]);
    }

    protected function payload(array $lineOverrides = []): array
    {
        return [
            'purchase_ref' => 'REF-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchase_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'items' => [array_merge([
                'item_type' => 'drug',
                'item_id' => $this->drug->id,
                'batch_number' => 'B-1',
                'quantity_received' => 10,
                'quantity_unit' => 'tablet',
                'cost_per_unit' => 20,
                'selling_price_per_unit' => 30,
                'expiry_date' => now()->addYear()->toDateString(),
            ], $lineOverrides)],
        ];
    }

    public function test_purchase_is_saved_when_every_line_has_an_expiry_date(): void
    {
        $this->actingAs($this->clerk)
            ->post(route('purchases.store'), $this->payload())
            ->assertRedirect(route('purchases.index'))
            ->assertSessionHasNoErrors();

        $batch = PurchaseBatch::first();
        $this->assertNotNull($batch->expiry_date);
    }

    public function test_purchase_is_rejected_when_a_line_has_no_expiry_date(): void
    {
        $payload = $this->payload();
        unset($payload['items'][0]['expiry_date']);

        $this->actingAs($this->clerk)
            ->post(route('purchases.store'), $payload)
            ->assertSessionHasErrors('items.0.expiry_date');

        $this->assertDatabaseCount('purchase_batches', 0);
    }

    public function test_an_empty_expiry_date_is_rejected_the_same_way(): void
    {
        $this->actingAs($this->clerk)
            ->post(route('purchases.store'), $this->payload(['expiry_date' => '']))
            ->assertSessionHasErrors('items.0.expiry_date');

        $this->assertDatabaseCount('purchase_batches', 0);
    }

    public function test_the_error_message_is_readable_not_a_raw_field_path(): void
    {
        $payload = $this->payload();
        unset($payload['items'][0]['expiry_date']);

        $response = $this->actingAs($this->clerk)
            ->from(route('purchases.create'))
            ->post(route('purchases.store'), $payload);

        $response->assertRedirect(route('purchases.create'));
        $response->assertSessionHasErrors([
            'items.0.expiry_date' => 'Every item line needs an expiry date.',
        ]);
    }

    public function test_an_expiry_date_in_the_past_is_still_rejected(): void
    {
        $this->actingAs($this->clerk)
            ->post(route('purchases.store'), $this->payload([
                'expiry_date' => now()->subDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('items.0.expiry_date');

        $this->assertDatabaseCount('purchase_batches', 0);
    }

    public function test_the_form_marks_the_expiry_field_as_required(): void
    {
        $this->actingAs($this->clerk)
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('name="items[0][expiry_date]"', false);
    }
}
