<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * PART C — seed the default expense categories. Idempotent: re-running
 * never duplicates or overwrites renames/deactivations made by the owner.
 * Each category is matched to an expense account in the chart of accounts;
 * missing accounts are created (codes from the 2026_10_11_000007 migration).
 */
class ExpenseCategorySeeder extends Seeder
{
    public const CATEGORIES = [
        ['name' => 'Rent',              'account_code' => '6000', 'account_name' => 'Rent Expense'],
        ['name' => 'Electricity',       'account_code' => '6010', 'account_name' => 'Electricity Expense'],
        ['name' => 'Water',             'account_code' => '6020', 'account_name' => 'Water Expense'],
        ['name' => 'Salaries',          'account_code' => '6030', 'account_name' => 'Salaries Expense'],
        ['name' => 'Transport',         'account_code' => '6040', 'account_name' => 'Transport Expense'],
        ['name' => 'Internet and Phone','account_code' => '6050', 'account_name' => 'Internet & Phone Expense'],
        ['name' => 'Other',             'account_code' => '6090', 'account_name' => 'Other Expense'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $cat) {
            // Make sure the GL account exists first (defensive: works even
            // if the migration above hasn't run on a pre-existing DB).
            Account::firstOrCreate(
                ['code' => $cat['account_code']],
                ['name' => $cat['account_name'], 'type' => 'expense', 'active' => true,
                 'description' => 'Recorded through the Expenses page']
            );

            ExpenseCategory::firstOrCreate(
                ['name' => $cat['name']],
                ['account_code' => $cat['account_code'], 'active' => true]
            );
        }
    }
}
