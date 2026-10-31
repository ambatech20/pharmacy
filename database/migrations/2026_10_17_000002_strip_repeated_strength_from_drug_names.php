<?php

use App\Models\Drug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove a strength that is repeated inside a drug's own name.
     *
     * The catalogue renders "name (strength)", so a drug named
     * "Paracetamol 500 mg" with a strength of "500 mg" read as
     * "Paracetamol 500 mg (500 mg)" - wasted space in the item dropdown, which
     * also has to fit the dispensing marker.
     *
     * Only a name that ENDS with its own strength is touched, so "Vitamin B
     * Complex" (strength "B1/B6/B12") and "Oral Rehydration Salts" are left
     * exactly as they are.
     */
    public function up(): void
    {
        DB::table('drugs')
            ->whereNotNull('strength')
            ->where('strength', '!=', '')
            ->orderBy('id')
            ->each(function ($drug) {
                $strength = trim((string) $drug->strength);
                $name = trim((string) $drug->name);

                if ($strength === '' || $name === '') {
                    return;
                }

                $suffix = ' ' . $strength;
                if (! str_ends_with($name, $suffix)) {
                    return;
                }

                $trimmed = trim(substr($name, 0, -strlen($suffix)));
                if ($trimmed === '') {
                    return;
                }

                DB::table('drugs')->where('id', $drug->id)->update(['name' => $trimmed]);
            });
    }

    public function down(): void
    {
        // Put the strength back on the end of the name.
        DB::table('drugs')
            ->whereNotNull('strength')
            ->where('strength', '!=', '')
            ->orderBy('id')
            ->each(function ($drug) {
                $strength = trim((string) $drug->strength);
                $name = trim((string) $drug->name);

                if ($strength === '' || $name === '' || str_ends_with($name, ' ' . $strength)) {
                    return;
                }

                DB::table('drugs')->where('id', $drug->id)->update(['name' => $name . ' ' . $strength]);
            });
    }
};
