<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PART E — sidebar keeps its scroll position across page loads, and PART D's
 * plain-language + CSS-variable rules on the screens the owner and clerks use.
 */
class SidebarTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $clerk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);
        $branch = Branch::create(['name' => 'Main Branch', 'address' => 'Addis', 'phone' => '0911']);
        Supplier::create(['name' => 'Medi Supply']);

        $this->owner = User::factory()->create(['role' => 'owner_admin', 'branch_id' => null]);
        $this->clerk = User::factory()->create(['role' => 'pharmacist_clerk', 'branch_id' => $branch->id]);
    }

    public function test_the_scrolling_sidebar_element_is_marked(): void
    {
        $html = $this->actingAs($this->owner)->get(route('dashboard'))->content();

        $this->assertStringContainsString('data-sidebar-nav', $html);
        // The nav is the element that scrolls; it must not gain its own page scroll.
        $this->assertStringContainsString('overflow-y-auto', $html);
    }

    public function test_the_restore_script_runs_from_the_sidebar_markup_not_from_app_js(): void
    {
        $html = $this->actingAs($this->owner)->get(route('dashboard'))->content();

        $sidebarEnd = strpos($html, '</aside>');
        $script = strpos($html, 'pmas.sidebarScroll');
        $this->assertNotFalse($sidebarEnd, 'Sidebar markup missing.');
        $this->assertNotFalse($script, 'Scroll-restore script missing.');
        // Inline right after the sidebar so it restores before first paint.
        $this->assertGreaterThan($sidebarEnd, $script);
        $this->assertLessThan(strpos($html, '</body>'), $script);

        foreach (['sessionStorage', 'scrollTop', 'pagehide', 'data-nav-active', 'try {', 'catch'] as $needle) {
            $this->assertStringContainsString($needle, substr($html, $sidebarEnd, strpos($html, '</body>') - $sidebarEnd), "Expected $needle in the sidebar script.");
        }
    }

    public function test_the_current_page_item_is_marked_even_on_child_routes(): void
    {
        $html = $this->actingAs($this->owner)->get(route('purchases.create'))->content();
        $normalized = preg_replace('/\s+/', ' ', $html);

        // purchases/create must highlight "Purchases", not nothing.
        $this->assertStringContainsString(
            '<a href="' . route('purchases.index') . '" data-nav-active',
            $normalized
        );
    }

    public function test_the_finance_group_is_not_shown_to_clerks(): void
    {
        $text = $this->visibleText($this->actingAs($this->clerk)->get(route('dashboard'))->content());

        // Clerks cannot open those pages, and their labels use accounting terms.
        foreach (['Journal Entries', 'Chart of Accounts', 'Finance'] as $label) {
            $this->assertStringNotContainsString(mb_strtolower($label), $text);
        }

        // Owners still see the group, in the same order, with plain wording.
        $ownerText = $this->visibleText($this->actingAs($this->owner)->get(route('dashboard'))->content());
        $this->assertStringContainsString('finance', $ownerText);
        $this->assertStringContainsString('accounting records', $ownerText);
    }

    public function test_owner_and_clerk_screens_avoid_accounting_terms(): void
    {
        $routes = [
            'dashboard', 'purchases.index', 'purchases.create', 'expenses.index', 'expenses.create',
            'alerts.index', 'supplier-owed.index', 'returns.customer.create', 'returns.supplier.create',
        ];

        foreach ([[$this->owner, $routes], [$this->clerk, ['dashboard', 'purchases.index', 'purchases.create', 'alerts.index']]] as [$user, $list]) {
            foreach ($list as $name) {
                $text = $this->visibleText($this->actingAs($user)->get(route($name))->content());
                foreach (['accounts payable', 'debit', 'credit entry', 'journal'] as $term) {
                    $this->assertStringNotContainsString(
                        $term,
                        $text,
                        sprintf('"%s" leaked onto %s for role %s.', $term, $name, $user->role)
                    );
                }
            }
        }
    }

    /**
     * Only what the user actually reads — route slugs and posted field names
     * may keep technical words, the visible text may not.
     */
    protected function visibleText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $html);

        return mb_strtolower(trim(preg_replace('/\s+/', ' ', strip_tags($html))));
    }

    public function test_brand_colors_reach_the_page_through_css_variables(): void
    {
        $this->actingAs($this->owner)->put(route('settings.branding.update'), [
            'business_name' => 'Blue Horizon Pharmacy',
            'brand_primary_color' => '#2F6FB4',
            'brand_accent_color' => '#14213D',
            'supplier_alert_days' => 7,
        ])->assertSessionHasNoErrors();

        $this->assertSame('#2F6FB4', Setting::value('brand_primary_color'));

        $html = $this->actingAs($this->owner)->get(route('purchases.index'))->content();

        // The chosen colour is published as the variable the utilities read...
        $this->assertStringContainsString('--brand-primary: #2F6FB4', $html);
        $this->assertStringContainsString('--brand-accent: #14213D', $html);
        // ...and no screen still paints the old hard-coded brand colour.
        $this->assertStringNotContainsString('#12A594', $html);
        $this->assertStringNotContainsString('#101B2D', $html);
        $this->assertStringContainsString('var(--brand-primary)', $html);
    }

    public function test_rebranding_needs_only_settings_changes(): void
    {
        // A different pharmacy: name, logo-free, colours — no code touched.
        $this->actingAs($this->owner)->put(route('settings.branding.update'), [
            'business_name' => 'Other Pharmacy PLC',
            'brand_primary_color' => '#7A3FB0',
            'brand_accent_color' => '#221133',
            'business_phone' => '0900000000',
            'business_address' => 'Bole, Addis Ababa',
        ])->assertSessionHasNoErrors();

        $login = $this->get(route('login'))->content();
        $this->assertStringContainsString('Other Pharmacy PLC', $login);
        $this->assertStringContainsString('--brand-primary: #7A3FB0', $login);
    }

    public function test_the_sidebar_is_split_into_collapsible_sections(): void
    {
        $sections = $this->sidebarSections($this->actingAs($this->owner)->get(route('dashboard'))->content());

        $this->assertSame(['overview', 'operations', 'catalog', 'finance', 'system'], array_keys($sections));
        foreach ($sections as $name => $section) {
            $this->assertGreaterThan(0, $section['links'], "Section $name lost its links.");
        }
        // Clerks never see the accounting section at all.
        $clerkSections = $this->sidebarSections($this->actingAs($this->clerk)->get(route('dashboard'))->content());
        $this->assertArrayNotHasKey('finance', $clerkSections);
    }

    public function test_only_the_section_of_the_current_page_starts_open(): void
    {
        $sections = $this->sidebarSections($this->actingAs($this->owner)->get(route('purchases.create'))->content());

        $this->assertSame('true', $sections['operations']['expanded']);
        $this->assertFalse($sections['operations']['hidden']);
        foreach (['overview', 'catalog', 'finance', 'system'] as $closed) {
            $this->assertSame('false', $sections[$closed]['expanded'], "$closed should start closed.");
            $this->assertTrue($sections[$closed]['hidden'], "$closed's items should be hidden.");
        }
    }

    public function test_a_direct_link_to_a_page_opens_its_own_section(): void
    {
        // Bookmarks and back-button loads never land on a hidden menu item.
        $sections = $this->sidebarSections($this->actingAs($this->owner)->get(route('expenses.index'))->content());

        $this->assertFalse($sections['catalog']['hidden']);
        $this->assertTrue($sections['system']['hidden']);
    }

    public function test_the_open_section_is_restored_before_paint_and_kept_per_session(): void
    {
        $html = $this->actingAs($this->owner)->get(route('dashboard'))->content();
        $start = strpos($html, '</aside>');
        $script = substr($html, $start, strpos($html, '</body>') - $start);

        // Inline next to the sidebar markup, not in the bundled app.js.
        $this->assertLessThan(strpos($html, '</body>'), strpos($html, 'pmas.sidebarOpenSection'));
        foreach (['pmas.sidebarOpenSection', 'data-nav-section-toggle', 'aria-expanded', '.hidden =', 'sessionStorage', 'try {', 'catch'] as $needle) {
            $this->assertStringContainsString($needle, $script, "Expected \"$needle\" in the sidebar script.");
        }
    }

    public function test_supplier_reminder_count_stays_visible_while_its_section_is_closed(): void
    {
        \App\Models\PurchaseBatch::create([
            'branch_id' => \App\Models\Branch::first()->id,
            'drug_id' => \App\Models\Drug::create(['name' => 'Amoxicillin', 'generic_name' => 'Amoxicillin', 'category_id' => \App\Models\DrugCategory::create(['name' => 'Antibiotic'])->id, 'unit_size' => 10, 'minimum_stock_level' => 0, 'cost_price' => 5, 'selling_price' => 8])->id,
            'supplier_id' => Supplier::first()->id,
            'batch_number' => 'B-BADGE', 'quantity_received' => 10, 'quantity_remaining' => 10,
            'cost_per_unit' => 5, 'selling_price_per_unit' => 8, 'total_cost' => 500,
            'payment_method' => \App\Models\Payment::PAY_LATER, 'payment_status' => 'unpaid',
            'amount_paid' => 0, 'due_date' => now()->addDay(), 'purchase_date' => now(),
            'expiry_date' => now()->addYear(),
        ]);

        // The dashboard sits in Overview, so Catalog is closed on this page.
        $sections = $this->sidebarSections($this->actingAs($this->owner)->get(route('dashboard'))->content());

        $this->assertTrue($sections['catalog']['hidden']);
        $this->assertSame('1', $sections['catalog']['badge']);
    }

    /**
     * @return array<string, array{expanded: string, hidden: bool, links: int, badge: string}>
     */
    protected function sidebarSections(string $html): array
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($document);

        $sections = [];
        foreach ($xpath->query('//*[@data-nav-section]') as $section) {
            $toggle = $xpath->query('.//*[@data-nav-section-toggle]', $section)->item(0);
            $items = $xpath->query('.//*[@data-nav-section-items]', $section)->item(0);
            $this->assertNotNull($toggle, 'Section without a toggle button.');
            $this->assertNotNull($items, 'Section without an items container.');

            $sections[$section->getAttribute('data-nav-section')] = [
                'expanded' => $toggle->getAttribute('aria-expanded'),
                'hidden' => $items->hasAttribute('hidden'),
                'links' => $xpath->query('.//a', $section)->length,
                'badge' => trim($xpath->query('.//span[contains(@class,"bg-red-500")]', $toggle)->item(0)?->textContent ?? ''),
            ];
        }

        return $sections;
    }
}
