<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PART A — make sure the single "Owed to Suppliers" liability account (code
 * 2000) exists on databases seeded before AccountSeeder listed it. Pay-later
 * purchases post against this one account for every supplier (supplier_id is
 * stored on the purchase rows themselves — we never create one account per
 * supplier). Idempotent: does nothing if the code is already there.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('accounts')->where('code', '2000')->exists();

        if (!$exists) {
            DB::table('accounts')->insert([
                'code' => '2000',
                'name' => 'Accounts Payable',
                'type' => 'liability',
                'description' => 'Owed to suppliers',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive both ways: never delete a real ledger account here.
    }
};
