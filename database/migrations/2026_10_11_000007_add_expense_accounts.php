<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

/**
 * PART C — expense accounts in the chart of accounts. Idempotent and
 * non-destructive: only inserts codes that don't exist yet (same pattern
 * as 2026_08_31_000005_add_digital_payment_accounts / 000008).
 */
return new class extends Migration
{
    public const ACCOUNTS = [
        ['code' => '6000', 'name' => 'Rent Expense',          'description' => 'Shop / office rent'],
        ['code' => '6010', 'name' => 'Electricity Expense',   'description' => 'Power bills'],
        ['code' => '6020', 'name' => 'Water Expense',         'description' => 'Water bills'],
        ['code' => '6030', 'name' => 'Salaries Expense',      'description' => 'Staff salaries and wages'],
        ['code' => '6040', 'name' => 'Transport Expense',     'description' => 'Delivery and travel'],
        ['code' => '6050', 'name' => 'Internet & Phone Expense', 'description' => 'Connectivity bills'],
        ['code' => '6090', 'name' => 'Other Expense',         'description' => 'Anything not covered above'],
    ];

    public function up(): void
    {
        foreach (self::ACCOUNTS as $account) {
            Account::firstOrCreate(
                ['code' => $account['code']],
                ['name' => $account['name'], 'type' => 'expense', 'description' => $account['description'], 'active' => true]
            );
        }
    }

    public function down(): void
    {
        // Non-destructive downgrade: leave accounts in place (they may hold ledger history).
    }
};
