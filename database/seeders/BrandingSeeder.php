<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * PART D — first client branding. Idempotent: it only fills values that are
 * not set yet, so re-running never overwrites changes the owner made on the
 * Settings page, and nothing is hard-coded elsewhere in the app.
 */
class BrandingSeeder extends Seeder
{
    public function run(): void
    {
        // insertOrIgnore-style default: only seed when the key doesn't exist.
        if (Setting::value('business_name') === null) {
            Setting::put('business_name', config('pmas.default_business_name', 'PMAS'));
        }

        foreach ([
            'brand_primary_color' => config('pmas.default_brand_primary_color', '#12A594'),
            'brand_accent_color' => config('pmas.default_brand_accent_color', '#101B2D'),
            'supplier_alert_days' => '7',
            'expiry_alert_days' => '90',
            'receipt_footer' => 'Thank you! Take your medicines as directed.',
        ] as $key => $default) {
            if (Setting::value($key) === null) {
                Setting::put($key, $default);
            }
        }
    }
}
