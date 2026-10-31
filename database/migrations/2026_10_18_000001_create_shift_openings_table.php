<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The drawer count that starts a shift.
     *
     * Replaces the old Opening Balances screen, which asked for eight figures
     * and most of them were numbers the books could not act on. This asks the
     * one thing a cashier actually knows at 8am - what is in the till - and
     * that figure is what makes the end-of-shift variance meaningful.
     *
     * opening_cash is a baseline, NOT a transaction. The float is already in
     * the cash account from wherever it came from, so posting it again here
     * would double-count it every single morning.
     */
    public function up(): void
    {
        Schema::create('shift_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');

            $table->timestamp('opened_at');
            // What was physically counted in the drawer at that moment.
            $table->decimal('opening_cash', 12, 2);

            // Null while the shift is still running. Closing the till stamps
            // this, which is what lets the next login know a new shift is due.
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('till_closing_id')
                ->nullable()
                ->constrained('till_closings')
                ->nullOnDelete();

            $table->timestamps();

            // "Does this cashier have a shift still open?" runs on every page
            // load, so it gets an index of its own.
            $table->index(['user_id', 'branch_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_openings');
    }
};
