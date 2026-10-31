<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many months a payment covers. Rent and similar bills are often paid
     * 3, 6 or 12 months in advance, so the reminder has to move by the number
     * of months actually paid, not always by one.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedTinyInteger('paid_months')->default(1)->after('repeat_monthly');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('paid_months');
        });
    }
};
