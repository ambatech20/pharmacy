<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            if (!Schema::hasColumn('drugs', 'unit_of_measure')) {
                $table->string('unit_of_measure')->default('pcs')->after('category_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            if (Schema::hasColumn('drugs', 'unit_of_measure')) {
                $table->dropColumn('unit_of_measure');
            }
        });
    }
};
