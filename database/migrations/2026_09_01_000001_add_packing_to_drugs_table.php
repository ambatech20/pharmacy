<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            if (!Schema::hasColumn('drugs', 'units_per_strip')) {
                $table->unsignedInteger('units_per_strip')->default(1)->after('unit_of_measure');
            }
            if (!Schema::hasColumn('drugs', 'strips_per_pack')) {
                $table->unsignedInteger('strips_per_pack')->default(1)->after('units_per_strip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            if (Schema::hasColumn('drugs', 'strips_per_pack')) {
                $table->dropColumn('strips_per_pack');
            }
            if (Schema::hasColumn('drugs', 'units_per_strip')) {
                $table->dropColumn('units_per_strip');
            }
        });
    }
};
