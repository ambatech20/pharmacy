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

        $exists = DB::table('accounts')->where('code', '5100')->exists();
        if ($exists) {
            return;
        }

        $row = [
            'name' => 'Inventory Shrinkage',
            'code' => '5100',
            'type' => 'expense',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('accounts', 'description')) {
            $row['description'] = 'Write-off of damaged, lost, or expired stock at cost';
        }
        if (Schema::hasColumn('accounts', 'active')) {
            $row['active'] = true;
        }

        DB::table('accounts')->insert($row);
    }

    public function down(): void
    {
        if (Schema::hasTable('accounts')) {
            DB::table('accounts')->where('code', '5100')->delete();
        }
    }
};
