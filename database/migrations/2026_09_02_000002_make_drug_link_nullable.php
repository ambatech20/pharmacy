<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_batches', function (Blueprint $table) {
            $table->dropForeign(['drug_id']);
            $table->unsignedBigInteger('drug_id')->nullable()->change();
            $table->foreign('drug_id')->references('id')->on('drugs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_batches', function (Blueprint $table) {
            $table->dropForeign(['drug_id']);
            $table->unsignedBigInteger('drug_id')->nullable(false)->change();
            $table->foreign('drug_id')->references('id')->on('drugs')->cascadeOnDelete();
        });
    }
};
