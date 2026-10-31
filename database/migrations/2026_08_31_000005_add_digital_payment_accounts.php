<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accounts')) {
            return;
        }

        $now = now();
        $accounts = [
            ['code' => '1010', 'name' => 'Telebirr', 'type' => 'asset', 'description' => 'Sales collected via Telebirr'],
            ['code' => '1011', 'name' => 'CBE Birr', 'type' => 'asset', 'description' => 'Sales collected via CBE Birr'],
            ['code' => '1012', 'name' => 'Bank', 'type' => 'asset', 'description' => 'Sales collected via bank transfer'],
            ['code' => '1013', 'name' => 'Card', 'type' => 'asset', 'description' => 'Sales collected via card'],
        ];

        foreach ($accounts as $account) {
            $exists = DB::table('accounts')->where('code', $account['code'])->exists();
            if ($exists) {
                continue;
            }

            $row = [
                'name' => $account['name'],
                'code' => $account['code'],
                'type' => $account['type'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('accounts', 'description')) {
                $row['description'] = $account['description'];
            }
            if (Schema::hasColumn('accounts', 'active')) {
                $row['active'] = true;
            }

            DB::table('accounts')->insert($row);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('accounts')) {
            return;
        }

        DB::table('accounts')->whereIn('code', ['1010', '1011', '1012', '1013'])->delete();
    }
};
