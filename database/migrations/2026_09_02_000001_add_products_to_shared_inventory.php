<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_categories')) {
            Schema::create('product_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('sku')->nullable()->unique();
                $table->foreignId('category_id')->constrained('product_categories')->restrictOnDelete();
                $table->string('brand')->nullable();
                $table->text('description')->nullable();
                $table->decimal('purchase_price', 10, 2)->default(0);
                $table->decimal('selling_price', 10, 2)->default(0);
                $table->decimal('minimum_stock', 10, 2)->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('purchase_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_batches', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('drug_id')->constrained('products')->restrictOnDelete();
            }
            if (!Schema::hasColumn('purchase_batches', 'expiry_date')) {
                $table->date('expiry_date')->nullable();
            }
            $table->unsignedBigInteger('drug_id')->nullable()->change();
            $table->dropForeign(['drug_id']);
            $table->foreign('drug_id')->references('id')->on('drugs')->nullOnDelete();
        });

        Schema::table('issued_items', function (Blueprint $table) {
            if (!Schema::hasColumn('issued_items', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('purchase_batch_id')->constrained('products')->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('issued_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });

        Schema::table('purchase_batches', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
            $table->dropForeign(['drug_id']);
            $table->foreign('drug_id')->references('id')->on('drugs')->cascadeOnDelete();
            $table->date('expiry_date')->nullable(false)->change();
        });

        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
