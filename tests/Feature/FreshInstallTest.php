<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the two things a fresh deployment depends on: that migrations alone
 * leave the database usable (the core posting accounts exist before any
 * seeding), and that ProductionSeeder produces a real install without the demo
 * catalog.
 */
class FreshInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_alone_guarantee_the_core_posting_accounts(): void
    {
        // RefreshDatabase migrates but never seeds, so this is what a
        // `php artisan migrate` only deployment looks like.
        foreach (['1001', '1002', '1200', '3000', '4000', '5000', '5200'] as $code) {
            $this->assertDatabaseHas('accounts', ['code' => $code]);
        }
    }

    public function test_production_seeder_creates_essentials_without_demo_data(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->assertDatabaseHas('branches', ['name' => 'Main Branch']);
        $this->assertDatabaseHas('users', ['role' => 'owner_admin']);
        $this->assertTrue(
            ExpenseCategory::count() > 0,
            'Expected the default expense categories to be seeded.'
        );

        // The demo catalog must stay out of production.
        $this->assertDatabaseCount('drugs', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_production_seeder_is_idempotent(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->seed(ProductionSeeder::class);

        $this->assertDatabaseCount('branches', 1);
        $this->assertSame(1, User::where('role', 'owner_admin')->count());
        $this->assertSame(1, Account::where('code', '1001')->count());
    }
}
