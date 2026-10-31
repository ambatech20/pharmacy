<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

/**
 * Cash Over/Short — the account a till close posts its variance to so the
 * ledger cash balance matches the physical drawer. A shortage debits it (an
 * expense); an overage credits it. Idempotent and non-destructive, same
 * pattern as the other account migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Account::firstOrCreate(
            ['code' => '5200'],
            [
                'name' => 'Cash Over/Short',
                'type' => 'expense',
                'description' => 'Difference between counted and expected cash at till close',
                'active' => true,
            ]
        );
    }

    public function down(): void
    {
        // Non-destructive downgrade: the account may already hold ledger history.
    }
};
