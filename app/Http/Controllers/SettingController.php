<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * PART D — Branding / business settings. Owner/Admin only (routes enforce
 * the role). Everything about the business (name, logo, colors, contact
 * details, receipt text) lives here as key/value settings — nothing is
 * hard-coded, so another pharmacy can be branded by changing these
 * settings alone.
 */
class SettingController extends Controller
{
    /** Keys that may be written through this page, and their rules. */
    protected const TEXT_FIELDS = [
        'business_name' => 'required|string|max:100',
        'business_phone' => 'nullable|string|max:30',
        'business_address' => 'nullable|string|max:255',
        'receipt_header' => 'nullable|string|max:255',
        'receipt_footer' => 'nullable|string|max:255',
        // Receipts: the VAT percentage already included in the prices shown.
        // 0 or empty means "don't break VAT out on receipts".
        'vat_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
        // Part B — how many days ahead supplier payment reminders appear.
        'supplier_alert_days' => 'nullable|integer|min:1|max:90',
        // How many days ahead the "expiring soon" alert looks. Only the three
        // offered windows are accepted.
        'expiry_alert_days' => 'nullable|integer|in:30,60,90',
    ];

    public function edit()
    {
        $settings = [
            'business_name' => Setting::value('business_name', config('pmas.default_business_name')),
            'business_phone' => Setting::value('business_phone'),
            'business_address' => Setting::value('business_address'),
            'receipt_header' => Setting::value('receipt_header'),
            'receipt_footer' => Setting::value('receipt_footer'),
            'vat_rate_percent' => Setting::value('vat_rate_percent', '0'),
            'brand_primary_color' => Setting::value('brand_primary_color', config('pmas.default_brand_primary_color')),
            'brand_accent_color' => Setting::value('brand_accent_color', config('pmas.default_brand_accent_color')),
            'logo_path' => Setting::value('logo_path'),
            'supplier_alert_days' => Setting::value('supplier_alert_days', '7'),
            'expiry_alert_days' => Setting::value('expiry_alert_days', '90'),
        ];

        return view('settings.branding', compact('settings'));
    }

    public function update(Request $request)
    {
        $hex = '#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})';

        $data = $request->validate([
            ...self::TEXT_FIELDS,
            'brand_primary_color' => ['required', 'string', 'regex:/^' . $hex . '$/'],
            'brand_accent_color' => ['required', 'string', 'regex:/^' . $hex . '$/'],
            // Logo: images only, max 2 MB, stored on the public disk.
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => 'nullable|boolean',
        ], [
            'brand_primary_color.regex' => 'Please use a valid color code like #12A594.',
            'brand_accent_color.regex' => 'Please use a valid color code like #101B2D.',
            'logo.image' => 'The logo must be an image (JPG, PNG or WebP).',
            'logo.max' => 'The logo must be smaller than 2 MB.',
            'business_name.required' => 'Please enter the business name.',
            'vat_rate_percent.numeric' => 'Please enter the VAT percentage as a number, like 15.',
            'vat_rate_percent.max' => 'The VAT percentage must be less than 100.',
        ]);

        // --- Text / color settings ---
        foreach (array_filter(self::TEXT_FIELDS, fn ($k) => array_key_exists($k, $data), ARRAY_FILTER_USE_KEY) as $key => $rule) {
            Setting::put($key, $data[$key] ?? null);
        }
        Setting::put('brand_primary_color', $data['brand_primary_color']);
        Setting::put('brand_accent_color', $data['brand_accent_color']);

        // Expiry alert window: only 30/60/90 are allowed (validated above);
        // empty falls back to the 90-day default.
        Setting::put('expiry_alert_days', (string) ($data['expiry_alert_days'] ?? 90));

        // --- Logo upload (validated above; stored safely under an ASCII name) ---
        if ($request->hasFile('logo')) {
            $old = Setting::value('logo_path');
            $path = $request->file('logo')->store('branding', 'public');
            Setting::put('logo_path', $path);
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        } elseif ($request->boolean('remove_logo')) {
            $old = Setting::value('logo_path');
            Setting::put('logo_path', null);
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }

        return back()->with('success', 'Settings saved. The whole system now uses your business name, logo and colors.');
    }

    public function editIntegration()
    {
        $settings = [
            'payment_verifier_enabled' => Setting::value('payment_verifier_enabled', '0'),
            'payment_verifier_webhook_secret' => Setting::value('payment_verifier_webhook_secret', ''),
        ];

        $webhookUrl = url('/api/payment-verifier/webhook');

        return view('settings.integration', compact('settings', 'webhookUrl'));
    }

    public function updateIntegration(Request $request)
    {
        $data = $request->validate([
            'payment_verifier_enabled' => 'required|in:0,1',
            // Turning the integration on without a shared secret would leave a
            // verifier able to reach us with nobody able to tell them apart, so
            // the form refuses that combination outright. 16 characters keeps a
            // pasted value from being something guessable like "secret".
            'payment_verifier_webhook_secret' => [
                'required_if:payment_verifier_enabled,1',
                'nullable',
                'string',
                'min:16',
                'max:255',
            ],
        ], [
            'payment_verifier_webhook_secret.required_if' => 'Set a webhook secret before turning the integration on - without one, anyone could post to the webhook.',
            'payment_verifier_webhook_secret.min' => 'The webhook secret must be at least 16 characters.',
        ]);

        Setting::put('payment_verifier_enabled', $data['payment_verifier_enabled']);
        Setting::put('payment_verifier_webhook_secret', $data['payment_verifier_webhook_secret'] ?? '');

        return back()->with('success', 'P-Check integration settings saved.');
    }
}
