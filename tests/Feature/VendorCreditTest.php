<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The vendor credit: "by AMBA Solutions" under the brand title on the login
 * page, and "Powered by AMBA Solutions" on every page, both linking to the
 * vendor's site.
 */
class VendorCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_credits_the_vendor_under_the_brand_title(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('by AMBA Solutions')
            ->assertSee('Powered by')
            ->assertSee('AMBA Solutions')
            ->assertSee('https://amba.site.je', false);
    }

    public function test_authenticated_pages_carry_the_powered_by_footer(): void
    {
        $branch = Branch::create(['name' => 'Main Branch']);
        $user = User::factory()->create(['role' => 'owner_admin', 'branch_id' => $branch->id]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Powered by')
            ->assertSee('AMBA Solutions')
            ->assertSee('https://amba.site.je', false);
    }
}
