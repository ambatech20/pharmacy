<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_batches', 'quantity_remaining')) {
                $table->integer('quantity_remaining')->nullable()->after('quantity_received');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_batches', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_batches', 'quantity_remaining')) {
                $table->dropColumn('quantity_remaining');
            }
        });
    }
};
