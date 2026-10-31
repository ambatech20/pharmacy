<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\PurchaseBatch;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "expiring soon" alert defaults to a 90-day window and can be narrowed to
 * 30 or 60 days from the Alerts page. The pharmacy default is set on the
 * Settings page; the Alerts page selector only changes the current view.
 */
class ExpiryAlertTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
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
        ]);
        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password',
            'role' => User::ROLE_OWNER_ADMIN, 'branch_id' => null,
        ]);
    }

    /** A batch whose batch number is also its expiry marker, for clean asserts. */
    protected function batch(string $number, int $expiresInDays, int $quantity = 10): PurchaseBatch
    {
        return PurchaseBatch::create([
            'drug_id' => $this->drug->id,
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'batch_number' => $number,
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
            'cost_per_unit' => 10,
            'selling_price_per_unit' => 15,
            'expiry_date' => now()->addDays($expiresInDays),
            'purchase_date' => now(),
        ]);
    }

    public function test_default_window_is_ninety_days(): void
    {
        $this->batch('EXP10', 10);
        $this->batch('EXP45', 45);
        $this->batch('EXP75', 75);
        $this->batch('EXP200', 200);

        $this->actingAs($this->owner)->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('Expiring Within 90 Days')
            ->assertSee('EXP10')
            ->assertSee('EXP45')
            ->assertSee('EXP75')
            ->assertDontSee('EXP200');
    }

    public function test_thirty_day_window_hides_stock_expiring_later(): void
    {
        $this->batch('EXP10', 10);
        $this->batch('EXP45', 45);

        $this->actingAs($this->owner)->get(route('alerts.index', ['expiry' => 30]))
            ->assertOk()
            ->assertSee('Expiring Within 30 Days')
            ->assertSee('EXP10')
            ->assertDontSee('EXP45');
    }

    public function test_sixty_day_window_sits_between_the_other_two(): void
    {
        $this->batch('EXP10', 10);
        $this->batch('EXP45', 45);
        $this->batch('EXP75', 75);

        $this->actingAs($this->owner)->get(route('alerts.index', ['expiry' => 60]))
            ->assertOk()
            ->assertSee('Expiring Within 60 Days')
            ->assertSee('EXP10')
            ->assertSee('EXP45')
            ->assertDontSee('EXP75');
    }

    public function test_a_window_that_is_not_thirty_sixty_or_ninety_falls_back_to_the_default(): void
    {
        $this->batch('EXP75', 75);

        $this->actingAs($this->owner)->get(route('alerts.index', ['expiry' => 45]))
            ->assertOk()
            ->assertSee('Expiring Within 90 Days')
            ->assertSee('EXP75');
    }

    public function test_owner_can_change_the_default_window_from_settings(): void
    {
        $this->batch('EXP45', 45);

        $this->actingAs($this->owner)->put(route('settings.branding.update'), [
            'business_name' => 'PMAS',
            'brand_primary_color' => '#12A594',
            'brand_accent_color' => '#101B2D',
            'expiry_alert_days' => 60,
        ])->assertRedirect();

        $this->assertSame('60', Setting::value('expiry_alert_days'));

        // The saved default now drives the Alerts page...
        $this->actingAs($this->owner)->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('Expiring Within 60 Days');

        // ...and the dashboard card, so the two never disagree.
        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Next 60 days');
    }

    public function test_settings_reject_a_window_that_is_not_offered(): void
    {
        $this->actingAs($this->owner)->put(route('settings.branding.update'), [
            'business_name' => 'PMAS',
            'brand_primary_color' => '#12A594',
            'brand_accent_color' => '#101B2D',
            'expiry_alert_days' => 45,
        ])->assertSessionHasErrors('expiry_alert_days');
    }

    public function test_alerts_page_offers_all_three_windows(): void
    {
        $this->actingAs($this->owner)->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('30 days')
            ->assertSee('60 days')
            ->assertSee('90 days');
    }

    public function test_settings_page_shows_the_expiry_window_selector(): void
    {
        $this->actingAs($this->owner)->get(route('settings.branding'))
            ->assertOk()
            ->assertSee('Expiry alert window')
            ->assertSee('90 days');
    }
}
