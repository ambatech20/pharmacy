<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value settings store (Part B: reminder window; Part D: branding).
 * Non-destructive; safe on a database that already has data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            return;
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Sensible default so the Alerts page / sidebar badge works out of
        // the box; owner can change it later (Part D settings page).
        DB::table('settings')->insertOrIgnore([
            ['key' => 'supplier_alert_days', 'value' => '7', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
