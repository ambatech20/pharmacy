<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_returns')) {
            Schema::create('customer_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->onDelete('cascade');
                $table->foreignId('issued_item_id')->constrained()->onDelete('cascade');
                $table->foreignId('purchase_batch_id')->constrained()->onDelete('cascade');
                $table->foreignId('branch_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
                $table->integer('quantity');
                $table->decimal('unit_cost', 10, 2);
                $table->decimal('cost_amount', 12, 2);
                $table->decimal('refund_amount', 12, 2);
                $table->string('refund_method');
                $table->string('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('supplier_returns')) {
            Schema::create('supplier_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_batch_id')->constrained()->onDelete('cascade');
                $table->foreignId('supplier_id')->nullable()->constrained()->onDelete('set null');
                $table->foreignId('branch_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
                $table->integer('quantity');
                $table->decimal('unit_cost', 10, 2);
                $table->decimal('total_amount', 12, 2);
                $table->string('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_returns');
        Schema::dropIfExists('supplier_returns');
    }
};
