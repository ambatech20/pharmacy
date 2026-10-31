<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-line discount, stored per BASE unit, alongside the price actually
     * charged. Only an Owner/Admin may set one (enforced in the controller);
     * the column exists so the sale line and the receipt can show what was
     * taken off, and so the activity log has a value to point at.
     */
    public function up(): void
    {
        Schema::table('issued_items', function (Blueprint $table) {
            $table->decimal('unit_discount', 10, 2)->default(0)->after('selling_price_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('issued_items', function (Blueprint $table) {
            $table->dropColumn('unit_discount');
        });
    }
};
