<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The drugs side now carries medical supplies, supplements and infant formula
 * as well as medicines. `kind` is what the list badge and filter read; `sku`
 * lets a row keep the code it had while it lived on the products side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->string('kind')->default('medicine')->after('form');
            $table->string('sku')->nullable()->unique()->after('kind');
        });

        DB::table('drugs')->whereNull('kind')->update(['kind' => 'medicine']);
    }

    public function down(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn(['kind', 'sku']);
        });
    }
};
