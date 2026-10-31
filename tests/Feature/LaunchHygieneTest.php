<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Launch hygiene: the PWA manifest is generated (not a static file with Blade
 * syntax baked in), unknown URLs show a branded 404 rather than Laravel's
 * default page, and crawlers are kept out of the private app.
 *
 * The icon tests here exist because every way of getting them wrong fails
 * silently: the site still loads, it just has a blank tab and installs with a
 * generic icon that nobody notices until they look for it.
 */
class LaunchHygieneTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_generated_with_the_brand_name(): void
    {
        Setting::put('business_name', 'City Care Pharmacy');

        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $this->assertStringContainsString('application/manifest+json', (string) $response->headers->get('Content-Type'));

        $manifest = $response->json();
        $this->assertSame('City Care Pharmacy', $manifest['name']);
    }

    public function test_manifest_never_leaks_blade_syntax(): void
    {
        // The old public/manifest.json contained "{{ business_name | default(...) }}"
        // which a browser read literally, because static files are never compiled.
        $body = $this->get('/manifest.webmanifest')->getContent();

        $this->assertStringNotContainsString('{{', $body);
        $this->assertStringNotContainsString('default(', $body);
    }

    /**
     * Chrome will not offer a proper install without a 192 and a 512 icon, and
     * a raster icon has to declare its real pixel size. `"sizes": "any"` means
     * a scalable image, so a PNG labelled that way is ignored and the
     * installed app falls back to a generic blank icon - which is what the
     * single logo-based entry used to do.
     */
    public function test_the_manifest_declares_installable_icons(): void
    {
        $icons = $this->get('/manifest.webmanifest')->json()['icons'];

        $sizes = array_column($icons, 'sizes');

        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertNotContains('any', $sizes, 'A PNG declared as "any" is ignored by Chrome.');

        // Every declared file has to exist, or the install quietly falls back
        // to a generic icon.
        foreach ($icons as $icon) {
            $this->assertFileExists(
                public_path(ltrim($icon['src'], '/')),
                $icon['src'] . ' is declared in the manifest but missing from public/'
            );
        }
    }

    /**
     * A launcher crops maskable icons to a circle or squircle, so they are
     * generated separately with the artwork inset into the safe zone. Declaring
     * one edge-to-edge image as "any maskable" is what made the installed icon
     * look wrong.
     */
    public function test_the_manifest_keeps_maskable_icons_separate(): void
    {
        $icons = $this->get('/manifest.webmanifest')->json()['icons'];

        $maskable = array_filter($icons, fn ($icon) => ($icon['purpose'] ?? '') === 'maskable');

        $this->assertNotEmpty($maskable, 'Android needs a maskable icon of its own.');

        foreach ($maskable as $icon) {
            $this->assertStringContainsString('maskable', $icon['src'], 'Maskable icons must be the inset versions.');
        }
    }

    public function test_the_icon_files_are_the_size_they_claim(): void
    {
        $expected = [
            'icon-192.png' => 192,
            'icon-512.png' => 512,
            'icon-maskable-192.png' => 192,
            'icon-maskable-512.png' => 512,
            'apple-touch-icon.png' => 180,
        ];

        foreach ($expected as $file => $size) {
            $path = public_path($file);

            $this->assertFileExists($path);

            $dimensions = getimagesize($path);

            $this->assertSame($size, $dimensions[0], "$file width");
            $this->assertSame($size, $dimensions[1], "$file height");
        }
    }

    /**
     * favicon.ico spent its whole life as a zero-byte placeholder, and browsers
     * cache icons much harder than ordinary files: a browser that fetched the
     * empty one keeps showing a blank tab until the address changes. The
     * version comes from the file's own timestamp, so regenerating the icons
     * busts the cache without anyone remembering to edit a number.
     */
    public function test_the_icons_are_served_from_a_versioned_address(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertMatchesRegularExpression('#href="/favicon\.ico\?v=[^"]+"#', $html);
        $this->assertMatchesRegularExpression('#href="/icon-32\.png\?v=[^"]+"#', $html);
        $this->assertMatchesRegularExpression('#href="/apple-touch-icon\.png\?v=[^"]+"#', $html);
    }

    public function test_unknown_url_shows_the_branded_404_page(): void
    {
        // Tests run with the local .env, which has APP_DEBUG=true; force the
        // production rendering path so the branded view is exercised.
        config(['app.debug' => false]);

        $this->get('/a-page-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('We couldn’t find that page');
    }

    public function test_robots_txt_keeps_crawlers_out(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Disallow: /', $robots);
    }
}
