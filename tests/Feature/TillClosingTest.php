<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\User;
use App\Services\AccountingService;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TillClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_till_close_overage_is_posted_to_cash_and_cash_over_short(): void
    {
        $this->seed(AccountSeeder::class);
        $branch = Branch::create(['name' => 'Main Branch']);
        $clerk = User::factory()->create(['role' => 'pharmacist_clerk', 'branch_id' => $branch->id]);

        // No sales today, so expected cash is 0 and counting 100 is a 100 overage.
        $this->actingAs($clerk)
            ->post(route('till-closings.store'), ['counted_cash' => 100])
            ->assertRedirect(route('till-closings.index'));

        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'till_closing',
            'branch_id' => $branch->id,
        ]);

        $cash = Account::where('code', '1001')->firstOrFail();
        $overShort = Account::where('code', '5200')->firstOrFail();

        $this->assertEquals(100.00, (float) $cash->ledgers()->sum('debit'));
        $this->assertEquals(100.00, (float) $overShort->ledgers()->sum('credit'));
    }

    public function test_a_cash_shortage_debits_cash_over_short_and_credits_cash(): void
    {
        $branch = Branch::create(['name' => 'Main Branch']);

        (new AccountingService())->recordTillVariance(-30, $branch->id, null);

        $cash = Account::where('code', '1001')->firstOrFail();
        $overShort = Account::where('code', '5200')->firstOrFail();

        $this->assertEquals(30.00, (float) $cash->ledgers()->sum('credit'));
        $this->assertEquals(30.00, (float) $overShort->ledgers()->sum('debit'));
    }

    public function test_a_matched_till_posts_no_adjustment(): void
    {
        $branch = Branch::create(['name' => 'Main Branch']);

        (new AccountingService())->recordTillVariance(0, $branch->id, null);

        $this->assertDatabaseCount('journal_entries', 0);
    }
}
