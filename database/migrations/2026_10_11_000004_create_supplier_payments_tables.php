<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PART B — paying suppliers.
 *
 * supplier_payments           : one row per payment issued to a supplier
 *                               (date, method, reference, check details).
 * supplier_payment_allocations: how that payment was spread across purchase
 *                               groups (purchase_ref) of the supplier.
 *
 * Balances are always calculated from purchase_batches (amount_paid per
 * line); these tables are the audit trail + allocation record, never an
 * editable balance.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supplier_payments')) {
            Schema::create('supplier_payments', function (Blueprint $table) {
                $table->id();
                $table->string('payment_number', 40)->unique();
                $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->date('payment_date');
                $table->decimal('amount', 12, 2); // total handed over
                $table->decimal('amount_applied', 12, 2)->default(0); // sum of allocations
                $table->decimal('overpayment', 12, 2)->default(0); // amount - applied (advance)
                $table->string('payment_method', 30); // cash | telebirr | ... | check
                $table->string('payment_reference', 255)->nullable();
                $table->string('check_number', 100)->nullable();
                $table->date('check_date')->nullable();   // shown as "Check date" — post-dated checks simply carry a future date here
                $table->string('check_bank', 255)->nullable();
                $table->text('note')->nullable();
                $table->foreignId('journal_entry_id')->nullable();
                $table->timestamps();

                $table->index(['supplier_id', 'payment_date']);
                $table->index('branch_id');
            });
        }

        if (!Schema::hasTable('supplier_payment_allocations')) {
            Schema::create('supplier_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_payment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('purchase_batch_id')->constrained()->cascadeOnDelete();
                $table->string('purchase_ref', 40)->nullable()->index(); // invoice group this line belongs to
                $table->decimal('amount', 12, 2);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
    }
};
