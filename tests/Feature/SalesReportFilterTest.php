<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\IssuedItem;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The month / year filter on the sales report.
 *
 * The total has to follow the filter too, not just the table, and the filter
 * has to survive paging. A month on its own means the current year.
 */
class SalesReportFilterTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected PurchaseBatch $batch;
    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $drug = Drug::create([
            'name' => 'Paracetamol',
            'strength' => '500mg',
            'unit_of_measure' => 'tablet',
            'units_per_strip' => 10,
            'strips_per_pack' => 20,
            'category_id' => DrugCategory::create(['name' => 'Pain relief'])->id,
        ]);

        $this->batch = PurchaseBatch::create([
            'drug_id' => $drug->id,
            'supplier_id' => Supplier::create(['name' => 'Medi Supply'])->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'B1',
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'cost_per_unit' => 10,
            'selling_price_per_unit' => 15,
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_date' => now()->subMonths(6)->toDateString(),
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => 'secret1234',
            'role' => 'owner_admin',
            'branch_id' => $this->branch->id,
        ]);
    }

    protected function sale(string $issueDate, float $total): IssuedItem
    {
        return IssuedItem::create([
            'purchase_batch_id' => $this->batch->id,
            'branch_id' => $this->branch->id,
            'quantity_issued' => 1,
            'selling_price_per_unit' => $total,
            'total_amount' => $total,
            'issue_date' => $issueDate,
        ]);
    }

    public function test_no_filter_shows_every_sale(): void
    {
        $this->sale(now()->subMonths(2)->toDateTimeString(), 100);
        $this->sale(now()->subMonth()->toDateTimeString(), 250);

        $this->actingAs($this->owner)->get(route('reports.sales'))
            ->assertOk()
            ->assertSee('ETB 350.00')
            ->assertSee('All time');
    }

    public function test_a_month_filter_narrows_the_rows_and_the_total(): void
    {
        $this->sale(now()->subMonths(2)->startOfMonth()->toDateTimeString(), 100);
        $this->sale(now()->startOfMonth()->toDateTimeString(), 250);

        $this->actingAs($this->owner)->get(route('reports.sales', [
            'month' => now()->month,
            'year' => now()->year,
        ]))
            ->assertOk()
            ->assertSee('ETB 250.00')
            ->assertSee(now()->format('F') . ' ' . now()->year);
    }

    public function test_a_year_filter_covers_all_twelve_months(): void
    {
        $year = now()->year;
        $this->sale(now()->subMonths(2)->startOfMonth()->toDateTimeString(), 100);
        $this->sale(now()->startOfMonth()->toDateTimeString(), 250);
        $this->sale(now()->subYear()->startOfMonth()->toDateTimeString(), 999);

        $this->actingAs($this->owner)->get(route('reports.sales', ['year' => $year]))
            ->assertOk()
            ->assertSee('ETB 350.00')
            ->assertSee('All of ' . $year);
    }

    public function test_a_month_without_a_year_falls_back_to_the_current_year(): void
    {
        $this->sale(now()->subYear()->startOfMonth()->toDateTimeString(), 999);

        $this->actingAs($this->owner)->get(route('reports.sales', ['month' => (int) now()->month]))
            ->assertOk()
            ->assertSee('ETB 0.00');
    }

    public function test_a_nonsense_month_is_ignored_rather_than_erroring(): void
    {
        $this->sale(now()->toDateTimeString(), 250);

        $this->actingAs($this->owner)->get(route('reports.sales', ['month' => 13]))
            ->assertOk()
            ->assertSee('ETB 250.00')
            ->assertSee('All time');
    }

    public function test_the_filter_survives_paging(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->sale(now()->startOfMonth()->toDateTimeString(), 10);
        }

        $response = $this->actingAs($this->owner)->get(route('reports.sales', [
            'month' => now()->month,
            'year' => now()->year,
        ]));

        $response->assertOk();

        // Every page link carries the filter, so page 2 is still this month.
        $this->assertStringContainsString(
            'month=' . now()->month,
            $response->getContent(),
        );
    }

    /**
     * Tickboxes, not a range: January + March must not pick up February on
     * the way through, and the one total is the sum of the ticked months.
     */
    public function test_ticking_several_months_combines_them_and_skips_the_months_between(): void
    {
        $year = (int) now()->year;
        $this->sale(\Carbon\Carbon::create($year, 1, 15)->toDateTimeString(), 100);
        $this->sale(\Carbon\Carbon::create($year, 2, 15)->toDateTimeString(), 999);
        $this->sale(\Carbon\Carbon::create($year, 3, 15)->toDateTimeString(), 250);

        $this->actingAs($this->owner)->get(route('reports.sales', [
            'months' => [1, 3],
            'year' => $year,
        ]))
            ->assertOk()
            ->assertSee('ETB 350.00')
            ->assertDontSee('ETB 1,349.00')
            ->assertSee('January, March ' . $year);
    }
}
