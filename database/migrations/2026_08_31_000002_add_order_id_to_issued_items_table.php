<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issued_items', function (Blueprint $table) {
            if (!Schema::hasColumn('issued_items', 'order_id')) {
                // Nullable: existing rows recorded before this migration
                // have no order to link to. Every new sale from here on
                // will always set it via the updated DispenseController.
                $table->foreignId('order_id')->nullable()->after('id')
                    ->constrained()->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('issued_items', function (Blueprint $table) {
            if (Schema::hasColumn('issued_items', 'order_id')) {
                $table->dropForeign(['order_id']);
                $table->dropColumn('order_id');
            }
        });
    }
};
