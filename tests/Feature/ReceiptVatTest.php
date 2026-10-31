<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\Order;
use App\Models\PurchaseBatch;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The prices the pharmacy charges already contain VAT, so the receipt has to
 * take it back out of them (amount x rate / (100 + rate)) and show the working.
 * Display only — what the customer pays and what posts to the accounts is
 * unchanged.
 */
class ReceiptVatTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): User
    {
        $this->seed(\Database\Seeders\AccountSeeder::class);

        return User::factory()->create(['role' => 'owner_admin', 'branch_id' => null]);
    }

    /**
     * @param array<int, array{int, float, float}> $lines qty, VAT-inclusive unit price, line total
     */
    protected function order(User $owner, array $lines): Order
    {
        $branch = Branch::create(['name' => 'Main', 'address' => 'Addis', 'phone' => '0911']);
        $supplier = Supplier::create(['name' => 'Test Supplier']);
        $category = DrugCategory::create(['name' => 'Analgesic']);

        $order = Order::create([
            'branch_id' => $branch->id,
            'user_id' => $owner->id,
            'total_amount' => round(array_sum(array_column($lines, 2)), 2),
            'status' => 'completed',
        ]);

        foreach ($lines as [$qty, $unitPrice, $lineTotal]) {
            $drug = Drug::create([
                'name' => 'Drug ' . $lineTotal, 'generic_name' => 'Generic', 'category_id' => $category->id,
                'unit_size' => 10, 'minimum_stock_level' => 0, 'cost_price' => 1, 'selling_price' => $unitPrice,
            ]);
            $batch = PurchaseBatch::create([
                'branch_id' => $branch->id, 'drug_id' => $drug->id, 'supplier_id' => $supplier->id,
                'batch_number' => 'B-' . uniqid(), 'quantity_received' => 100, 'quantity_remaining' => 100,
                'cost_per_unit' => 1, 'selling_price_per_unit' => $unitPrice, 'payment_status' => 'paid',
                'amount_paid' => 100, 'expiry_date' => now()->addYear(), 'purchase_date' => now(),
            ]);
            $order->items()->create([
                'order_id' => $order->id, 'branch_id' => $branch->id, 'purchase_batch_id' => $batch->id,
                'quantity_issued' => $qty, 'selling_price_per_unit' => $unitPrice,
                'total_amount' => $lineTotal, 'issue_date' => now(),
            ]);
        }

        return $order->fresh(['items']);
    }

    public function test_vat_is_taken_back_out_of_the_inclusive_prices_and_the_figures_add_up(): void
    {
        Setting::put('vat_rate_percent', '15');
        // 2 x 45.00, 1 x 12.34 and 3 x 2.50 — the odd ones catch bad rounding.
        $order = $this->order($this->owner(), [[2, 45, 90.00], [1, 12.34, 12.34], [3, 2.5, 7.50]]);

        $vat = $order->vatBreakdown();
        $this->assertSame(15.0, $vat['rate']);
        $this->assertSame(109.84, $vat['total']);
        $this->assertSame(14.33, $vat['vat']);
        $this->assertSame(95.51, $vat['net']);
        // Before VAT + VAT always equals the amount the customer paid.
        $this->assertSame(109.84, round($vat['net'] + $vat['vat'], 2));

        foreach ($order->items as $item) {
            $line = $vat['items'][$item->id];
            $this->assertSame(round((float) $item->total_amount, 2), round($line['net'] + $line['vat'], 2));
        }
        $this->assertSame(11.74, $vat['items'][$order->items[0]->id]['vat']);
        $this->assertSame(78.26, $vat['items'][$order->items[0]->id]['net']);
        $this->assertSame(1.61, $vat['items'][$order->items[1]->id]['vat']);
        $this->assertSame(0.98, $vat['items'][$order->items[2]->id]['vat']);
    }

    public function test_html_receipt_shows_vat_lines_when_a_rate_is_set(): void
    {
        Setting::put('vat_rate_percent', '15');
        $owner = $this->owner();
        $order = $this->order($owner, [[2, 45, 90.00]]);

        $html = $this->actingAs($owner)->get(route('dispense.receipt', $order))->content();
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html, ' ')));

        $this->assertStringContainsString('2 × $45.00 (incl. VAT) $90.00', $text);
        $this->assertStringContainsString('Before VAT $78.26 VAT 15% $11.74 Total (incl. VAT) $90.00', $text);
    }

    public function test_receipt_has_no_vat_until_the_owner_sets_a_rate(): void
    {
        $owner = $this->owner();
        $order = $this->order($owner, [[2, 45, 90.00]]);

        $this->assertNull($order->vatBreakdown());

        $html = $this->actingAs($owner)->get(route('dispense.receipt', $order))->content();
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html, ' ')));

        $this->assertStringNotContainsString('VAT', $text);
        $this->assertStringContainsString('Total $90.00', $text);

        // A rate of 0 means the same thing as no rate.
        Setting::put('vat_rate_percent', '0');
        $this->assertNull($order->fresh()->vatBreakdown());
    }

    public function test_escpos_receipt_carries_the_same_vat_lines(): void
    {
        Setting::put('vat_rate_percent', '15');
        $owner = $this->owner();
        $order = $this->order($owner, [[2, 45, 90.00]]);

        $bytes = $this->actingAs($owner)->get(route('dispense.receipt.escpos', $order))->content();

        $this->assertStringContainsString('2 x 45.00 incl. VAT', $bytes);
        $this->assertStringContainsString('VAT 11.74', $bytes);
        $this->assertStringContainsString('Before VAT', $bytes);
        $this->assertStringContainsString('VAT 15%', $bytes);
        $this->assertStringContainsString('TOTAL (incl. VAT)', $bytes);
        $this->assertStringContainsString('VAT = 90.00 x 15/115 = 11.74', $bytes);
        $this->assertStringNotContainsString('TOTAL 90.00', $bytes);
    }

    public function test_vat_rate_is_saved_from_business_settings(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put(route('settings.branding.update'), [
            'business_name' => 'PMAS',
            'brand_primary_color' => '#12A594',
            'brand_accent_color' => '#101B2D',
            'vat_rate_percent' => '15',
        ])->assertSessionHasNoErrors();

        $this->assertSame('15', Setting::value('vat_rate_percent'));
        $this->assertStringContainsString('VAT included in your selling prices', $this->actingAs($owner)->get(route('settings.branding'))->content());
    }

    public function test_a_vat_rate_of_100_or_more_or_a_non_number_is_rejected(): void
    {
        $owner = $this->owner();
        $base = [
            'business_name' => 'PMAS',
            'brand_primary_color' => '#12A594',
            'brand_accent_color' => '#101B2D',
        ];

        $this->actingAs($owner)->put(route('settings.branding.update'), $base + ['vat_rate_percent' => '150'])
            ->assertSessionHasErrors('vat_rate_percent');
        $this->actingAs($owner)->put(route('settings.branding.update'), $base + ['vat_rate_percent' => 'fifteen'])
            ->assertSessionHasErrors('vat_rate_percent');
        $this->assertSame('0', Setting::value('vat_rate_percent', '0'));
    }
}
