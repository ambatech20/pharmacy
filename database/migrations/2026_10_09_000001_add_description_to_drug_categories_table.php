<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('drug_categories', 'description')) {
            Schema::table('drug_categories', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('drug_categories', 'description')) {
            Schema::table('drug_categories', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};