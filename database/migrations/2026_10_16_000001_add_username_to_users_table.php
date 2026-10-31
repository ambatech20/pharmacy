<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Staff sign in with a username now, not an email address. The email is
     * kept on the account (it is still collected when a user is created) but is
     * no longer the login identifier.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username')->nullable()->unique()->after('name');
            });
        }

        $this->backfillUsernames();
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['username']);
                $table->dropColumn('username');
            });
        }
    }

    /**
     * Give every existing account a username so nobody is locked out by the
     * change: the part of their email before the @, lower-cased and cleaned up,
     * de-duplicated with a trailing number when two people share a handle.
     */
    protected function backfillUsernames(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $taken = [];
        foreach (DB::table('users')->whereNotNull('username')->pluck('username') as $name) {
            $taken[Str::lower($name)] = true;
        }

        $rows = DB::table('users')
            ->select('id', 'email', 'username')
            ->where(fn ($query) => $query->whereNull('username')->orWhere('username', ''))
            ->get();

        foreach ($rows as $row) {
            $base = Str::lower((string) Str::before((string) $row->email, '@'));
            $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?: 'user';

            $candidate = $base;
            $suffix = 1;
            while (isset($taken[$candidate])) {
                $candidate = $base . (++$suffix);
            }
            $taken[$candidate] = true;

            DB::table('users')->where('id', $row->id)->update(['username' => $candidate]);
        }
    }
};
