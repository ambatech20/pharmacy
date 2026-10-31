<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('drug_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('strength')->nullable();
            $table->string('form')->nullable();
            $table->foreignId('category_id')->constrained('drug_categories')->onDelete('cascade');
            $table->decimal('minimum_stock_level', 8, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->string('batch_number');
            $table->integer('quantity_received');
            $table->integer('quantity_remaining');
            $table->decimal('cost_per_unit', 10, 2);
            $table->decimal('selling_price_per_unit', 10, 2);
            $table->date('expiry_date')->nullable();
            $table->date('purchase_date');
            $table->timestamps();
        });

        Schema::create('stock_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_batch_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->enum('transaction_type', ['in', 'out', 'transfer_in', 'transfer_out']);
            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->timestamp('transaction_date');
            $table->timestamps();
        });

        Schema::create('issued_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_batch_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->integer('quantity_issued');
            $table->decimal('selling_price_per_unit', 10, 2);
            $table->decimal('total_amount', 12, 2);
            $table->timestamp('issue_date');
            $table->timestamps();
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('to_branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('purchase_batch_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2);
            $table->timestamp('transfer_date');
            $table->string('status')->default('completed');
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number')->unique();
            $table->date('date');
            $table->string('description');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->decimal('total_debit', 12, 2);
            $table->decimal('total_credit', 12, 2);
            $table->timestamps();
        });

        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->string('description');
            $table->timestamps();
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('role', ['owner_admin', 'branch_manager', 'pharmacist_clerk'])->default('pharmacist_clerk');
        });
    }

    public function down()
	{
        Schema::dropIfExists('ledgers');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('issued_items');
        Schema::dropIfExists('stock_cards');
        Schema::dropIfExists('purchase_batches');
        Schema::dropIfExists('drugs');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('drug_categories');
        Schema::dropIfExists('branches');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id', 'role']);
        });
    }
};