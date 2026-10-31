<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PART A — optional default credit period per supplier, used to pre-fill the
 * "Pay later" due date on the purchase form (e.g. 30 or 60 days).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('suppliers') && !Schema::hasColumn('suppliers', 'default_credit_days')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->unsignedSmallInteger('default_credit_days')->nullable()->after('phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('suppliers') && Schema::hasColumn('suppliers', 'default_credit_days')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('default_credit_days');
            });
        }
    }
};
