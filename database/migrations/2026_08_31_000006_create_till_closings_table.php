<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('till_closings')) {
            Schema::create('till_closings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('branch_id')->constrained()->onDelete('cascade');
                // Window this close covers: after the previous close (or
                // start of today), up to closed_at.
                $table->timestamp('period_start');
                // Nullable so the column does not rely on MySQL's implicit
                // zero-date default, which strict MySQL 8 rejects with
                // "Invalid default value" when a table has two timestamps.
                $table->timestamp('closed_at')->nullable();
                $table->decimal('expected_cash', 12, 2);
                $table->decimal('counted_cash', 12, 2);
                $table->decimal('variance', 12, 2);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('till_closings');
    }
};
