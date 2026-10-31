<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * The PWA web app manifest, generated on the fly so the installed app uses the
 * pharmacy's real branding (name, colour, logo) instead of a hard-coded one.
 *
 * This used to be a static public/manifest.json that contained Blade syntax
 * (`{{ business_name | default('...') }}`). A static file is never compiled, so
 * the literal placeholder string was what phones showed as the app name.
 */
class ManifestController extends Controller
{
    public function __invoke()
    {
        // Setting::value() already swallows a missing table, and logoPath()
        // checks the file exists, so this can't 500 a browser fetching it.
        $brandName = (string) Setting::value('business_name', config('pmas.default_business_name'));
        $logo = Setting::logoPath();

        $manifest = [
            'name' => $brandName,
            'short_name' => mb_substr($brandName, 0, 12),
            'description' => 'Pharmacy operations: medicines, retail products, stock, sales and suppliers.',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#edf4f3',
            'theme_color' => (string) Setting::value('brand_primary_color', config('pmas.default_brand_primary_color')),
            'icons' => $this->icons($logo),
        ];

        return response()->json(
            $manifest,
            200,
            ['Content-Type' => 'application/manifest+json'],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * The icon set produced by tools/assets/make-icons.ps1.
     *
     * Three details here are not optional, and getting any of them wrong fails
     * silently - the site still installs, it just installs with a generic
     * blank icon:
     *
     *   - Each raster icon declares its real pixel size. "sizes": "any" means
     *     a scalable image; a PNG labelled that way is not something Chrome
     *     will use for an installed app.
     *   - Maskable icons are listed separately. A launcher crops those to a
     *     circle or squircle, so their artwork is inset into the safe zone -
     *     one edge-to-edge image cannot serve both purposes, which is why
     *     declaring the logo as "any maskable" looked wrong.
     *   - Both 192 and 512 are present: Android wants 192, and Chrome will
     *     not offer a proper install without the larger one.
     *
     * Falls back to the uploaded logo so a fresh checkout, where the icon
     * files have not been generated yet, still produces a usable manifest.
     *
     * @return array<int, array<string, string>>
     */
    private function icons(?string $logo): array
    {
        $declared = [
            ['icon-192.png', '192x192', 'any'],
            ['icon-512.png', '512x512', 'any'],
            ['icon-maskable-192.png', '192x192', 'maskable'],
            ['icon-maskable-512.png', '512x512', 'maskable'],
        ];

        $icons = [];

        foreach ($declared as [$file, $sizes, $purpose]) {
            // Served from the web root: Apache's stock `Alias /icons/` owns
            // that path, so a public/icons/ folder would be invisible.
            if (! is_file(public_path($file))) {
                continue;
            }

            $icons[] = [
                'src' => '/' . $file,
                'sizes' => $sizes,
                'type' => 'image/png',
                'purpose' => $purpose,
            ];
        }

        if ($icons === []) {
            $icons[] = [
                'src' => $logo ? Storage::url($logo) : '/favicon.ico',
                'sizes' => 'any',
                'type' => $logo ? 'image/png' : 'image/x-icon',
            ];
        }

        return $icons;
    }
}
