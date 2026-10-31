<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every screen, every role, once.
 *
 * This exists because a 500 lived on /search-drug for the whole life of the
 * project and nothing noticed: no test hit the route, and the sale screen
 * filtered a preloaded <select> rather than calling it. A route that nothing
 * exercises is a route nobody has opened.
 *
 * So this walks every GET route as all three roles and asserts the two things
 * that should never be wrong:
 *
 *   - nobody gets a 500;
 *   - the role gate matches the middleware, in both directions. A role that
 *     the middleware forbids must not get 200, and a role it allows must not
 *     be turned away.
 */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** Routes that are not screens a signed-in user navigates to. */
    private const SKIP = [
        'up',                    // health check
        'sanctum.csrf-cookie',   // Sanctum
        'storage.local',         // Laravel's signed private-file serving
        'login',                 // the guest login page
    ];

    /** Every role that has a real user, and one route-allowed list per route. */
    private const ROLES = ['owner_admin', 'branch_manager', 'pharmacist_clerk'];

    protected array $ids = [];
    protected array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);

        $branch = Branch::create(['name' => 'Main Branch']);
        $category = DrugCategory::create(['name' => 'Antibiotics']);
        $productCategory = ProductCategory::create(['name' => 'Consumables']);
        $product = Product::create([
            'name' => 'Cotton Wool', 'category_id' => $productCategory->id, 'minimum_stock' => 5,
        ]);
        $supplier = Supplier::create(['name' => 'Medi Supply']);
        $customer = Customer::create(['name' => 'Walk-in']);
        $drug = Drug::create([
            'name' => 'Amox', 'strength' => '500mg', 'unit_of_measure' => 'capsule',
            'units_per_strip' => 10, 'strips_per_pack' => 10, 'minimum_stock_level' => 50,
            'category_id' => $category->id,
        ]);
        $order = Order::create([
            'branch_id' => $branch->id, 'customer_id' => $customer->id,
            'user_id' => null, 'total_amount' => 100,
        ]);
        $expenseCategory = ExpenseCategory::create(['name' => 'Rent', 'account_code' => '6000', 'active' => true]);
        $expense = Expense::create([
            'expense_date' => now()->toDateString(), 'expense_category_id' => $expenseCategory->id,
            'branch_id' => $branch->id, 'amount' => 6000, 'monthly_amount' => 6000,
            'payment_method' => 'cash', 'repeat_monthly' => true,
            'next_due_date' => now()->toDateString(), 'paid_months' => 1,
        ]);

        $this->users = [];
        foreach (self::ROLES as $role) {
            $this->users[$role] = User::create([
                'name' => ucfirst($role), 'username' => $role,
                'email' => $role . '@example.com', 'password' => 'secret1234',
                'role' => $role, 'branch_id' => $branch->id,
            ]);
        }
        $order->update(['user_id' => $this->users['owner_admin']->id]);

        $this->ids = [
            '{order}' => $order->id, '{category}' => $category->id, '{drug}' => $drug->id,
            '{product}' => $product->id, '{productCategory}' => $productCategory->id,
            '{supplier}' => $supplier->id, '{customer}' => $customer->id,
            '{user}' => $this->users['pharmacist_clerk']->id, '{expense}' => $expense->id,
        ];
    }

    /** @return array<int, array{name: ?string, uri: string, allowed: array<int, string>}> */
    protected function screens(): array
    {
        $screens = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods())) {
                continue;
            }

            $name = $route->getName();
            if (in_array($name, self::SKIP, true)) {
                continue;
            }

            $uri = $route->uri();
            foreach ($this->ids as $token => $value) {
                $uri = str_replace($token, (string) $value, $uri);
            }

            $screens[] = ['name' => $name, 'uri' => $uri, 'allowed' => $this->allowedRoles($route)];
        }

        return $screens;
    }

    /** @return array<int, string> */
    protected function allowedRoles($route): array
    {
        $allowed = self::ROLES;

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                $allowed = array_values(array_filter(
                    explode(',', substr($middleware, 5)),
                    fn ($role) => in_array($role, self::ROLES, true)
                ));
            }
        }

        return $allowed;
    }

    public function test_no_screen_throws_a_server_error_for_any_role(): void
    {
        $serverErrors = [];

        foreach ($this->screens() as $screen) {
            foreach (self::ROLES as $role) {
                $status = $this->actingAs($this->users[$role])->get('/' . $screen['uri'])->getStatusCode();

                if ($status >= 500) {
                    $serverErrors[] = sprintf('%s (%s) as %s returned %d', $screen['name'], $screen['uri'], $role, $status);
                }
            }
        }

        $this->assertSame([], $serverErrors, "Server errors:\n" . implode("\n", $serverErrors));
    }

    public function test_a_forbidden_role_never_gets_through(): void
    {
        $leaks = [];

        foreach ($this->screens() as $screen) {
            foreach (self::ROLES as $role) {
                if (in_array($role, $screen['allowed'], true)) {
                    continue;
                }

                $status = $this->actingAs($this->users[$role])->get('/' . $screen['uri'])->getStatusCode();

                // 302 is fine: the shift gate or a form redirect is not access.
                if (! in_array($status, [401, 403, 302], true)) {
                    $leaks[] = sprintf('%s (%s) allowed %s but answered %d', $screen['name'], $screen['uri'], $role, $status);
                }
            }
        }

        $this->assertSame([], $leaks, "Permission leaks:\n" . implode("\n", $leaks));
    }

    public function test_an_allowed_role_is_never_turned_away(): void
    {
        $blocked = [];

        foreach ($this->screens() as $screen) {
            foreach ($screen['allowed'] as $role) {
                $status = $this->actingAs($this->users[$role])->get('/' . $screen['uri'])->getStatusCode();

                if (! in_array($status, [200, 302], true)) {
                    $blocked[] = sprintf('%s (%s) blocks %s with %d', $screen['name'], $screen['uri'], $role, $status);
                }
            }
        }

        $this->assertSame([], $blocked, "Over-blocked:\n" . implode("\n", $blocked));
    }

    /**
     * Guards the sweep itself: if a route stopped being registered, or the
     * walk silently covered nothing, the other three tests would pass while
     * testing nothing.
     */
    public function test_the_sweep_actually_covers_the_screens(): void
    {
        $screens = $this->screens();

        $this->assertGreaterThan(50, count($screens), 'The sweep should cover most of the GET routes.');
        $this->assertNotEmpty(array_filter($screens, fn ($s) => $s['allowed'] === self::ROLES), 'At least one screen is open to all roles.');
        $this->assertNotEmpty(array_filter($screens, fn ($s) => $s['allowed'] === ['owner_admin']), 'At least one screen is owner-only.');
    }
}
