<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Session garbage collection is automatic (the session handler runs it on a
// lottery during normal requests), so there is no session:gc command to
// schedule. The cache prune is a harmless no-op unless a taggable store
// (Redis) is used, and is kept for parity with the Laravel default.
Schedule::command('cache:prune-stale-tags')->hourly();

// Nightly database backup (storage/app/backups, keeps BACKUP_KEEP files).
Schedule::command('pmas:backup')->dailyAt('02:30')->withoutOverlapping();
