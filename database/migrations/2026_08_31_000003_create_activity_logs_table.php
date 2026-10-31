<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
                $table->string('action'); // created | updated | deleted
                $table->string('model_type'); // e.g. 'Drug', 'PurchaseBatch'
                $table->unsignedBigInteger('model_id');
                $table->json('changes')->nullable(); // new values (create) or changed fields (update)
                $table->timestamps();

                $table->index(['model_type', 'model_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
