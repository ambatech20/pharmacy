<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which dashboard shortcuts this person wants.
     *
     * A JSON array of keys from DashboardController::AVAILABLE_SHORTCUTS, not
     * of URLs: a stored route name would break the moment anyone renamed a
     * route, and it could not be checked against the list the picker offers.
     *
     * Nullable, so an account that has never touched the picker falls back to
     * the default set rather than looking like an empty dashboard.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('dashboard_shortcuts')->nullable()->after('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_shortcuts');
        });
    }
};
