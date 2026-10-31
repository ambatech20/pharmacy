<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Amlo Denk was seeded as a single "Pack" sold whole, on the reading that
 * "tong" printed no count. "tong" is the 10x10 notation (the seeder's own
 * note), so a pack really is 10 strips of 10 tablets - and the shop sells
 * strips. Without the middle rung the till could only offer Pack and Tablet.
 *
 * Only the row with no stock is changed: re-describing a pack that already
 * has tablets on the shelf would silently reinterpret the quantity.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('drugs', 'unit_middle')) {
            return;
        }

        $drug = DB::table('drugs')
            ->where('name', 'Amlo Denk')
            ->where('strength', '5+5')
            ->where(function ($query) {
                $query->whereNull('unit_middle')->orWhere('unit_middle', '');
            })
            ->first(['id']);

        if (! $drug) {
            return;
        }

        if (DB::table('purchase_batches')->where('drug_id', $drug->id)->exists()) {
            return;
        }

        DB::table('drugs')->where('id', $drug->id)->update([
            'unit_top' => 'pack',
            'top_holds' => 10,
            'units_per_top' => 100,
            'unit_middle' => 'strip',
            'middle_holds' => 10,
            'unit_base' => 'tablet',
            'sell_top' => true,
            'sell_middle' => true,
            'sell_base' => true,
            // Mirrors read by the screens that have not been converted.
            'unit_of_measure' => 'tablet',
            'units_per_strip' => 10,
            'strips_per_pack' => 10,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('drugs', 'unit_middle')) {
            return;
        }

        DB::table('drugs')
            ->where('name', 'Amlo Denk')
            ->where('strength', '5+5')
            ->where('top_holds', 10)
            ->where('middle_holds', 10)
            ->update([
                'top_holds' => 1,
                'units_per_top' => 1,
                'unit_middle' => null,
                'middle_holds' => null,
                'unit_base' => 'pack',
                'sell_middle' => false,
                'sell_base' => false,
                'unit_of_measure' => 'pack',
                'units_per_strip' => 1,
                'strips_per_pack' => 1,
            ]);
    }
};
