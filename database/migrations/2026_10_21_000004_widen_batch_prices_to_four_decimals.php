<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A batch keeps its price per base unit. A pack of 28 tablets bought at
 * 120.00 is 4.2857 a tablet, and at two decimals that became 4.29 - so the
 * pack the till rebuilt from it cost 120.12, not the 120.00 on the invoice.
 *
 * Four decimals keep the round trip exact to the cent for any pack size the
 * shop uses (a 100-tablet pack at 120.00 is still exactly 1.20). Totals and
 * ledger amounts are computed from the invoice line, not from this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_batches', function (Blueprint $table) {
            $table->decimal('cost_per_unit', 12, 4)->change();
            $table->decimal('selling_price_per_unit', 12, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_batches', function (Blueprint $table) {
            $table->decimal('cost_per_unit', 10, 2)->change();
            $table->decimal('selling_price_per_unit', 10, 2)->change();
        });
    }
};
