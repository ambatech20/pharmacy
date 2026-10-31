<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Describe each drug's packing instead of assuming tablet -> strip -> pack.
 *
 * The old model had exactly one shape for every medicine: a base unit
 * (unit_of_measure), units_per_strip and strips_per_pack. That is right for a
 * box of blister strips and wrong for everything else - a bottle of syrup has
 * no strip, a box of 20 sachets has no strip, and a tin of vitamins is sold
 * whole. Worse, the unit dropdown offered "Strip" and "Pack" for all of them.
 *
 * The replacement describes one to three rungs, biggest first:
 *
 *   unit_top     pack / box / bottle / tin / vial / tube
 *   unit_middle  strip when the top holds strips, otherwise null
 *   unit_base    the thing actually counted: tablet, sachet, bottle, pack...
 *
 *   top_holds     how many of the next rung are in one top (e.g. 10 strips)
 *   middle_holds  how many base units are in one middle (e.g. 10 tablets)
 *
 * Stock is still stored only in base units. Nothing about the batches, stock
 * cards, sales or reports changes - this only decides how a quantity is typed
 * in and read out.
 *
 * The three old columns are deliberately kept. The model mirrors them on every
 * save, so the screens that have not been converted yet keep working while
 * this is rolled out one screen at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->string('unit_top', 20)->nullable()->after('strips_per_pack');
            $table->string('unit_middle', 20)->nullable()->after('unit_top');
            $table->string('unit_base', 20)->nullable()->after('unit_middle');

            $table->unsignedInteger('top_holds')->nullable()->after('unit_base');
            $table->unsignedInteger('middle_holds')->nullable()->after('top_holds');

            // Derived from the three rungs above. Kept as a column so the
            // low-stock queries can compare in SQL without loading every drug
            // into PHP first.
            $table->unsignedInteger('units_per_top')->default(1)->after('middle_holds');

            // What may leave the shelf. A pack is always sellable; the rungs
            // below it are a choice per drug, because you break some strips and
            // not others.
            $table->boolean('sell_top')->default(true)->after('units_per_top');
            $table->boolean('sell_middle')->default(true)->after('sell_top');
            $table->boolean('sell_base')->default(true)->after('sell_middle');
        });

        $this->backfill();
    }

    public function down(): void
    {
        // Put the minimum back into base units before its factor disappears.
        // The value was rounded up on the way in, so this restores the
        // threshold, not necessarily the exact figure that was typed.
        DB::table('drugs')->orderBy('id')->chunk(200, function ($drugs) {
            foreach ($drugs as $drug) {
                DB::table('drugs')->where('id', $drug->id)->update([
                    'minimum_stock_level' => (float) $drug->minimum_stock_level
                        * max(1, (int) $drug->units_per_top),
                ]);
            }
        });

        Schema::table('drugs', function (Blueprint $table) {
            $table->dropColumn([
                'unit_top',
                'unit_middle',
                'unit_base',
                'top_holds',
                'middle_holds',
                'units_per_top',
                'sell_top',
                'sell_middle',
                'sell_base',
            ]);
        });
    }

    /**
     * Translate the old geometry into the new description, and move
     * minimum_stock_level from base units into the top unit.
     *
     * Three cases, exactly the ones the old columns could express:
     *
     *   units_per_strip > 1            pack -> strips -> tablets
     *   units_per_strip = 1, packs > 1 box of 20 sachets
     *   both 1                         sold as it arrives (pack, bottle, tube)
     */
    private function backfill(): void
    {
        DB::table('drugs')->orderBy('id')->chunk(200, function ($drugs) {
            foreach ($drugs as $drug) {
                $base = strtolower(trim((string) $drug->unit_of_measure)) ?: 'tablet';
                $perStrip = max(1, (int) $drug->units_per_strip);
                $perPack = max(1, (int) $drug->strips_per_pack);
                $containers = ['pack', 'box', 'bottle', 'tin', 'vial', 'tube'];

                if ($perStrip > 1) {
                    // The full three rungs, which is what the old screens meant.
                    $row = [
                        'unit_top' => 'pack',
                        'unit_middle' => 'strip',
                        'unit_base' => $base,
                        'top_holds' => $perPack,
                        'middle_holds' => $perStrip,
                        'units_per_top' => $perPack * $perStrip,
                    ];
                } elseif ($perPack > 1) {
                    // No strip worth naming: a box of sachets, a pack of vials.
                    $row = [
                        'unit_top' => 'pack',
                        'unit_middle' => null,
                        'unit_base' => $base,
                        'top_holds' => $perPack,
                        'middle_holds' => null,
                        'units_per_top' => $perPack,
                    ];
                } else {
                    // One rung. A sealed pack, a bottle, a tube - counted as it
                    // arrives, never opened.
                    $top = in_array($base, $containers, true) ? $base : 'pack';
                    $row = [
                        'unit_top' => $top,
                        'unit_middle' => null,
                        'unit_base' => $top,
                        'top_holds' => null,
                        'middle_holds' => null,
                        'units_per_top' => 1,
                    ];
                }

                // Minimum stock now means "this many packs", not "this many
                // tablets", so the stored number has to shrink by the same
                // factor. Rounded up: half a pack is not a shelf count.
                $factor = max(1, (int) $row['units_per_top']);
                $oldMin = (float) $drug->minimum_stock_level;
                $row['minimum_stock_level'] = $oldMin > 0
                    ? (int) ceil($oldMin / $factor)
                    : 0;

                $row['sell_top'] = true;
                $row['sell_middle'] = $row['unit_middle'] !== null;
                $row['sell_base'] = true;

                DB::table('drugs')->where('id', $drug->id)->update($row);
            }
        });
    }
};
