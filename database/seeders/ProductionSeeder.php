<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Everything a real deployment needs to become usable, and nothing that is
 * demo data: the main branch, the chart of accounts, the default expense
 * categories, branding defaults and the first administrator.
 *
 * It deliberately does NOT run CatalogSeeder — a real pharmacy must not have
 * the demo drugs/products from CatalogSeeder inserted into its catalog. Use
 * the default DatabaseSeeder (which adds the demo catalog) for local dev.
 *
 * Idempotent: safe to re-run.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::firstOrCreate(
            ['name' => 'Main Branch'],
            ['address' => 'Addis Ababa', 'phone' => '0911000000']
        );

        // Chart of accounts the posting code expects (also guaranteed by the
        // ensure_core_posting_accounts migration, but kept here for setups
        // that seed without migrating).
        $this->call(AccountSeeder::class);

        // PART C — default expense categories + their GL accounts.
        $this->call(ExpenseCategorySeeder::class);

        // PART D — business name / colours / receipt text defaults.
        $this->call(BrandingSeeder::class);

        $this->createAdmin($branch->id);
    }

    protected function createAdmin(int $branchId): void
    {
        $email = config('pmas.admin_email') ?: 'admin@pmas.com';

        if (User::where('email', $email)->exists()) {
            return;
        }

        // The login identifier. Falls back to the email's local part, then
        // 'admin', so a fresh install always has something to sign in with.
        $username = Str::lower(trim((string) (config('pmas.admin_username') ?: Str::before($email, '@'))));
        if ($username === '') {
            $username = 'admin';
        }

        $base = $username;
        $suffix = 1;
        while (User::where('username', $username)->exists()) {
            $username = $base . (++$suffix);
        }

        $password = config('pmas.admin_password');
        $generated = false;

        if (!$password) {
            // No real secret is hard-coded: mint a strong one and show it once.
            $password = Str::password(16);
            $generated = true;
        }

        User::create([
            'name' => config('pmas.admin_name') ?: 'Admin User',
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'branch_id' => $branchId,
            'role' => User::ROLE_OWNER_ADMIN,
            'active' => true,
        ]);

        if ($generated) {
            $this->command?->warn("Created administrator {$username} ({$email}) with generated password: {$password}");
            $this->command?->warn('Store it now and change it after the first login.');
        } else {
            $this->command?->info("Created administrator {$username} ({$email}) using ADMIN_PASSWORD from the environment.");
        }
    }
}
