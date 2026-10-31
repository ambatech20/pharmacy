<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * These exact codes are hardcoded inside PurchaseController,
     * AccountingService::recordSale(), etc. Without these rows existing,
     * Account::where('code', ...)->firstOrFail() throws the moment a
     * purchase or sale tries to post — this seeder is what was missing.
     */
    public function run(): void
    {
        $accounts = [
            ['code' => '1001', 'name' => 'Cash',                'type' => 'asset',     'description' => 'Cash on hand'],
            ['code' => '1002', 'name' => 'Accounts Receivable',  'type' => 'asset',     'description' => 'Owed by customers'],
            ['code' => '1010', 'name' => 'Telebirr',             'type' => 'asset',     'description' => 'Sales collected via Telebirr'],
            ['code' => '1011', 'name' => 'CBE Birr',             'type' => 'asset',     'description' => 'Sales collected via CBE Birr'],
            ['code' => '1012', 'name' => 'Bank',                 'type' => 'asset',     'description' => 'Sales collected via bank transfer'],
            ['code' => '1013', 'name' => 'Card',                 'type' => 'asset',     'description' => 'Sales collected via card'],
            ['code' => '1200', 'name' => 'Inventory',            'type' => 'asset',     'description' => 'Drug stock on hand, at cost'],
            ['code' => '2000', 'name' => 'Accounts Payable',     'type' => 'liability', 'description' => 'Owed to suppliers'],
            ['code' => '3000', 'name' => 'Owner Equity',         'type' => 'equity',    'description' => 'Owner capital'],
            ['code' => '4000', 'name' => 'Sales Revenue',        'type' => 'revenue',   'description' => 'Revenue from drug sales'],
            ['code' => '5000', 'name' => 'Cost of Goods Sold',   'type' => 'expense',   'description' => 'Cost of drugs sold'],
            ['code' => '5100', 'name' => 'Inventory Shrinkage',  'type' => 'expense',   'description' => 'Write-off of damaged, lost, or expired stock at cost'],
            ['code' => '5200', 'name' => 'Cash Over/Short',      'type' => 'expense',   'description' => 'Difference between counted and expected cash at till close'],
        ];

        foreach ($accounts as $account) {
            Account::firstOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'description' => $account['description'],
                    'active' => true,
                ]
            );
        }
    }
}
