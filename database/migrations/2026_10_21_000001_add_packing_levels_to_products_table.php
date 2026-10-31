<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products get the same three packing rungs as drugs.
 *
 * Existing product rows were sold as one thing, so they start on a single
 * "piece" rung. The product form can then re-describe them as, say, a box of
 * 12 bottles without touching the stock already on the shelf: quantity is
 * still stored in the smallest unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('unit_of_measure')->nullable()->after('minimum_stock');
            $table->unsignedSmallInteger('units_per_strip')->default(1)->after('unit_of_measure');
            $table->unsignedSmallInteger('strips_per_pack')->default(1)->after('units_per_strip');

            $table->string('unit_top')->nullable()->after('strips_per_pack');
            $table->string('unit_middle')->nullable()->after('unit_top');
            $table->string('unit_base')->nullable()->after('unit_middle');
            $table->unsignedSmallInteger('top_holds')->nullable()->after('unit_base');
            $table->unsignedSmallInteger('middle_holds')->nullable()->after('top_holds');
            $table->unsignedInteger('units_per_top')->default(1)->after('middle_holds');

            $table->boolean('sell_top')->default(true)->after('units_per_top');
            $table->boolean('sell_middle')->default(false)->after('sell_top');
            $table->boolean('sell_base')->default(true)->after('sell_middle');
        });

        DB::table('products')->update([
            'unit_of_measure' => 'piece',
            'units_per_strip' => 1,
            'strips_per_pack' => 1,
            'unit_top' => 'piece',
            'unit_middle' => null,
            'unit_base' => 'piece',
            'top_holds' => 1,
            'middle_holds' => null,
            'units_per_top' => 1,
            'sell_top' => true,
            'sell_middle' => false,
            'sell_base' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'unit_of_measure', 'units_per_strip', 'strips_per_pack',
                'unit_top', 'unit_middle', 'unit_base',
                'top_holds', 'middle_holds', 'units_per_top',
                'sell_top', 'sell_middle', 'sell_base',
            ]);
        });
    }
};
