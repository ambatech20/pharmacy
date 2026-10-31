<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PART C — Expenses. Non-destructive: new tables only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // GL account this category posts to (created by the seeder if missing).
            $table->string('account_code', 10);
            $table->boolean('active')->default(true);
            // "Repeat monthly" reminder flag (optional feature).
            $table->boolean('allow_recurring')->default(false);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date');
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            // Same methods as purchases, but expenses are never "Pay later".
            $table->string('payment_method', 30);
            $table->string('payment_reference')->nullable();
            $table->string('check_number', 100)->nullable();
            $table->date('check_date')->nullable();
            $table->string('check_bank')->nullable();
            $table->text('note')->nullable();
            $table->boolean('repeat_monthly')->default(false);
            $table->date('next_due_date')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'expense_date']);
            $table->index(['expense_category_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
