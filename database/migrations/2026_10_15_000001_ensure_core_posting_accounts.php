<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The posting code (PurchaseController, AccountingService, ExpenseController)
 * hard-codes these account codes and looks them up with firstOrFail(). They
 * used to exist ONLY in AccountSeeder, so a database built with
 * `php artisan migrate` alone (no seed) crashed on the very first sale,
 * purchase, write-off or supplier return.
 *
 * This migration makes `migrate` sufficient on its own: it inserts any of the
 * core accounts that are missing, idempotently and non-destructively. The
 * method-specific accounts (1010-1013), the supplier liability (2000), the
 * shrinkage account (5100) and the expense accounts (6000-6090) are already
 * guaranteed by their own migrations.
 */
return new class extends Migration
{
    public const ACCOUNTS = [
        ['code' => '1001', 'name' => 'Cash',               'type' => 'asset',   'description' => 'Cash on hand'],
        ['code' => '1002', 'name' => 'Accounts Receivable', 'type' => 'asset',   'description' => 'Owed by customers'],
        ['code' => '1200', 'name' => 'Inventory',          'type' => 'asset',   'description' => 'Drug stock on hand, at cost'],
        ['code' => '3000', 'name' => 'Owner Equity',       'type' => 'equity',  'description' => 'Owner capital'],
        ['code' => '4000', 'name' => 'Sales Revenue',      'type' => 'revenue', 'description' => 'Revenue from drug sales'],
        ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'description' => 'Cost of drugs sold'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('accounts')) {
            return;
        }

        $hasDescription = Schema::hasColumn('accounts', 'description');
        $hasActive = Schema::hasColumn('accounts', 'active');

        foreach (self::ACCOUNTS as $account) {
            if (DB::table('accounts')->where('code', $account['code'])->exists()) {
                continue;
            }

            $row = [
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($hasDescription) {
                $row['description'] = $account['description'];
            }
            if ($hasActive) {
                $row['active'] = true;
            }

            DB::table('accounts')->insert($row);
        }
    }

    public function down(): void
    {
        // Non-destructive: any of these accounts may already hold ledger
        // history, so the downgrade deliberately leaves them in place.
    }
};
