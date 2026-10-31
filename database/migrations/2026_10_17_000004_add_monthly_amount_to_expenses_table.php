<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The rate this bill repeats at, per month.
     *
     * `amount` is what was actually paid - paying three months of rent at
     * 6,000 records 18,000, because that is the money that left the account.
     * `monthly_amount` keeps the monthly rate alongside it, so the reminder
     * still says "Rent - ETB 6,000 due ..." rather than quoting the lump sum.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('monthly_amount', 10, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('monthly_amount');
        });
    }
};
