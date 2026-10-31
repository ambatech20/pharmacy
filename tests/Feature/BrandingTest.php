<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): User
    {
        $this->seed(\Database\Seeders\AccountSeeder::class);

        return User::factory()->create(['role' => 'owner_admin', 'branch_id' => null]);
    }

    public function test_settings_page_is_owner_only(): void
    {
        $owner = $this->owner();
        $clerk = User::factory()->create(['role' => 'pharmacist_clerk']);
        $manager = User::factory()->create(['role' => 'branch_manager']);

        $this->actingAs($clerk)->get(route('settings.branding'))->assertForbidden();
        $this->actingAs($manager)->put(route('settings.branding.update'), ['business_name' => 'X'])->assertForbidden();
        $this->actingAs($owner)->get(route('settings.branding'))->assertOk();
    }

    public function test_business_name_shows_in_title_and_sidebar_after_save(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put(route('settings.branding.update'), [
            'business_name' => 'Green Leaf Pharmacy',
            'brand_primary_color' => '#12A594',
            'brand_accent_color' => '#101B2D',
            'supplier_alert_days' => 7,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Green Leaf Pharmacy', Setting::value('business_name'));

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Green Leaf Pharmacy')
            ->assertDontSee('<title>PMAS');
    }

    public function test_logo_upload_validated_and_stored(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        // Valid image accepted (real 1x1 PNG bytes so the image MIME rule passes without GD).
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $tmp = tempnam(sys_get_temp_dir(), 'logo').'.png';
        file_put_contents($tmp, $png);
        $this->actingAs($owner)->put(route('settings.branding.update'), [
            'business_name' => 'PMAS',
            'brand_primary_color' => '#12A594',
            'brand_accent_color' => '#101B2D',
            'logo' => new UploadedFile($tmp, 'logo.png', 'image/png', null, true),
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(Setting::value('logo_path'));
        Storage::disk('public')->assertExists(Setting::value('logo_path'));

        // Non-image rejected.
        $this->actingAs($owner)->post(route('settings.branding.update'), [
            'business_name' => 'PMAS',
            'brand_primary_color' => '#12A594',
            'brand_accent_color' => '#101B2D',
            'logo' => UploadedFile::fake()->create('virus.pdf', 100),
        ] + ['_method' => 'PUT'])->assertSessionHasErrors('logo');
    }

    public function test_logo_path_is_null_when_the_file_is_missing(): void
    {
        Storage::fake('public');

        Setting::put('logo_path', 'branding/gone.png');
        $this->assertNull(Setting::logoPath());

        Storage::disk('public')->put('branding/real.png', 'x');
        Setting::put('logo_path', 'branding/real.png');
        $this->assertSame('branding/real.png', Setting::logoPath());
    }

    public function test_login_page_does_not_render_a_missing_logo(): void
    {
        Storage::fake('public');
        Setting::put('logo_path', 'branding/gone.png');

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('branding/gone.png')
            ->assertSee('by AMBA Solutions');
    }

    public function test_invalid_color_rejected(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put(route('settings.branding.update'), [
            'business_name' => 'Test Shop',
            'brand_primary_color' => 'notacolor',
            'brand_accent_color' => '#101B2D',
        ])->assertSessionHasErrors('brand_primary_color');
    }

    public function test_branding_seeder_is_idempotent_and_does_not_overwrite(): void
    {
        $this->owner();
        $this->seed(\Database\Seeders\BrandingSeeder::class);
        $this->assertSame('PMAS', Setting::value('business_name'));

        Setting::put('business_name', 'My Own Name');
        $this->seed(\Database\Seeders\BrandingSeeder::class); // re-run must not overwrite
        $this->assertSame('My Own Name', Setting::value('business_name'));
    }

    public function test_receipt_uses_business_name_from_settings(): void
    {
        $owner = $this->owner();
        Setting::put('business_name', 'Sunrise Pharmacy');
        Setting::put('receipt_footer', 'No returns after 7 days.');

        // Build a minimal order through existing models so the receipt renders.
        $branch = \App\Models\Branch::create(['name' => 'Main', 'address' => 'Addis', 'phone' => '0911']);
        $drug = \App\Models\Drug::create(['name' => 'Paracetamol', 'generic_name' => 'Paracetamol', 'category_id' => \App\Models\DrugCategory::create(['name' => 'Analgesic'])->id, 'unit_size' => 10, 'minimum_stock_level' => 0, 'cost_price' => 5, 'selling_price' => 8]);
        $supplier = \App\Models\Supplier::create(['name' => 'Test Supplier']);
        $batch = \App\Models\PurchaseBatch::create(['branch_id' => $branch->id, 'drug_id' => $drug->id, 'supplier_id' => $supplier->id, 'batch_number' => 'B-'.uniqid(), 'quantity_received' => 10, 'quantity_remaining' => 10, 'cost_per_unit' => 5, 'selling_price_per_unit' => 8, 'payment_status' => 'paid', 'amount_paid' => 50, 'expiry_date' => now()->addYear(), 'purchase_date' => now()]);
        $order = \App\Models\Order::create(['branch_id' => $branch->id, 'user_id' => $owner->id, 'total_amount' => 8, 'payment_method' => 'cash']);
        $order->items()->create(['order_id' => $order->id, 'branch_id' => $branch->id, 'purchase_batch_id' => $batch->id, 'quantity_issued' => 1, 'unit_cost_price' => 5, 'selling_price_per_unit' => 8, 'total_amount' => 8, 'issue_date' => now()]);

        $this->actingAs($owner)->get(route('dispense.receipt', $order))
            ->assertOk()
            ->assertSee('Sunrise Pharmacy')
            ->assertSee('No returns after 7 days.')
            ->assertDontSee('>PMAS<');
    }
}
