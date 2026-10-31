<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false);
            }
            if (!Schema::hasColumn('customers', 'reminder_interval_days')) {
                $table->integer('reminder_interval_days')->nullable();
            }
            if (!Schema::hasColumn('customers', 'condition_note')) {
                // Free-text note e.g. "Diabetic — monthly refill", optional
                $table->string('condition_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            foreach (['is_recurring', 'reminder_interval_days', 'condition_note'] as $col) {
                if (Schema::hasColumn('customers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
