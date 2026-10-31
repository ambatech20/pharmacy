<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DrugCategory;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The browser's address book and password manager stay out of pharmacy data.
 *
 * Signing in still needs the saved username and password, so the login page is
 * the one place those hints belong. Every other form must render as "other" so
 * a saved login cannot appear over an item name, a batch number or the till
 * search - which on a shared counter could show a password to the next user.
 */
class BrowserAutofillTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);

        $this->branch = Branch::create(['name' => 'Main Branch']);

        // The drug and product forms send the user to a "create a category
        // first" page when none exist, so give each form one to open.
        DrugCategory::create(['name' => 'Antibiotics']);
        ProductCategory::create(['name' => 'Hygiene', 'active' => true]);

        $this->owner = User::create([
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => 'secret1234',
            'role' => 'owner_admin',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_login_keeps_the_saved_username_and_password(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('autocomplete="username"', false);
        $response->assertSee('autocomplete="current-password"', false);

        // The global guard is deliberately not loaded on the login page: it
        // would rewrite the password field to "new-password".
        $response->assertDontSee('data-no-browser-autofill', false);
        $response->assertDontSee('autocomplete="new-password"', false);
    }

    public function test_signed_in_layout_loads_the_global_guard(): void
    {
        $this->actingAs($this->owner)
            ->get(route('drugs.create'))
            ->assertOk()
            ->assertSee('data-no-browser-autofill', false);
    }

    public function test_drug_and_product_forms_opt_out_of_autofill(): void
    {
        $this->actingAs($this->owner)
            ->get(route('drugs.create'))
            ->assertOk()
            ->assertSee('autocomplete="off" data-form-type="other"', false)
            ->assertSeeInOrder(['name="sku"', 'data-lpignore="true"'], false);

        $this->actingAs($this->owner)
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('autocomplete="off" data-form-type="other"', false)
            ->assertSee('data-lpignore="true"', false);
    }

    public function test_till_search_and_walk_in_customer_fields_opt_out(): void
    {
        $response = $this->actingAs($this->owner)->get(route('dispense.create'));

        $response->assertOk();
        $response->assertSee('autocomplete="off" data-form-type="other"', false);
        $response->assertSeeInOrder(['id="catalog-search"', 'data-lpignore="true"'], false);
        $response->assertSeeInOrder(['name="new_customer_name"', 'data-lpignore="true"'], false);
        $response->assertSeeInOrder(['name="new_customer_phone"', 'data-lpignore="true"'], false);
    }

    public function test_purchase_reference_and_batch_fields_opt_out(): void
    {
        $response = $this->actingAs($this->owner)->get(route('purchases.create'));

        $response->assertOk();
        $response->assertSee('autocomplete="off" data-form-type="other"', false);
        $response->assertSeeInOrder(['name="payment_reference"', 'data-lpignore="true"'], false);
        $response->assertSeeInOrder(['name="supplier_invoice_no"', 'data-lpignore="true"'], false);
        $response->assertSeeInOrder(['items[0][batch_number]', 'data-lpignore="true"'], false);
    }

    public function test_customer_and_supplier_name_fields_opt_out(): void
    {
        $this->actingAs($this->owner)
            ->get(route('customers.create'))
            ->assertOk()
            ->assertSee('autocomplete="off" data-form-type="other"', false)
            ->assertSeeInOrder(['name="name"', 'data-lpignore="true"'], false);

        $this->actingAs($this->owner)
            ->get(route('suppliers.create'))
            ->assertOk()
            ->assertSee('autocomplete="off" data-form-type="other"', false)
            ->assertSeeInOrder(['name="name"', 'data-lpignore="true"'], false);
    }

    public function test_staff_account_forms_never_offer_a_saved_login(): void
    {
        // Creating a user: the username box must not fill with the admin's own
        // saved login, and both password boxes are new-password only.
        $this->actingAs($this->owner)
            ->get(route('users.create'))
            ->assertOk()
            ->assertSee('name="username" value="" autocomplete="off"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertDontSee('current-password', false);

        // Editing one: same rule, and an unchanged password stays untouched.
        $this->actingAs($this->owner)
            ->get(route('users.edit', $this->owner))
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertSeeInOrder(['name="username"', 'data-lpignore="true"'], false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertDontSee('current-password', false);
    }

    public function test_business_settings_opt_out(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.branding'))
            ->assertOk()
            ->assertSee('autocomplete="off" data-form-type="other"', false)
            ->assertSeeInOrder(['name="business_name"', 'data-lpignore="true"'], false)
            ->assertSeeInOrder(['name="business_address"', 'data-lpignore="true"'], false);
    }
}
