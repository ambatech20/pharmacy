<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_batches')) {
            Schema::table('purchase_batches', function (Blueprint $table) {
                $table->index('expiry_date');
                $table->index('purchase_date');
                $table->index(['payment_method', 'payment_status']);
                $table->index('due_date');
            });
        }

        if (Schema::hasTable('issued_items')) {
            Schema::table('issued_items', function (Blueprint $table) {
                $table->index('issue_date');
            });
        }

        if (Schema::hasTable('stock_cards')) {
            Schema::table('stock_cards', function (Blueprint $table) {
                $table->index(['reference_type', 'reference_id']);
            });
        }

        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->index(['reference_type', 'reference_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_batches')) {
            Schema::table('purchase_batches', function (Blueprint $table) {
                $table->dropIndex(['expiry_date']);
                $table->dropIndex(['purchase_date']);
                $table->dropIndex(['payment_method', 'payment_status']);
                $table->dropIndex(['due_date']);
            });
        }

        if (Schema::hasTable('issued_items')) {
            Schema::table('issued_items', function (Blueprint $table) {
                $table->dropIndex(['issue_date']);
            });
        }

        if (Schema::hasTable('stock_cards')) {
            Schema::table('stock_cards', function (Blueprint $table) {
                $table->dropIndex(['reference_type', 'reference_id']);
            });
        }

        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->dropIndex(['reference_type', 'reference_id']);
            });
        }
    }
};
