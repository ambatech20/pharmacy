<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\BackupHealth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The reminders are memoised inside the service. One instance per
        // request means the dashboard and the sidebar badge share that work
        // instead of each running the same open-purchase query.
        $this->app->singleton(\App\Services\SupplierOwedService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Development guardrail: a lazily-loaded relation (the classic N+1)
        // throws instead of quietly hammering the database. Disabled in
        // production so a surprise during a request can never take the app
        // down for real users.
        Model::preventLazyLoading(! app()->isProduction());

        View::composer('layouts.app', function ($view) {
            $badge = 0;
            $billsBadge = 0;
            $user = Auth::user();
            if ($user && $user->role === 'owner_admin') {
                try {
                    // Both tables exist once the migrations have run, but this
                    // composer runs on every signed-in page and each check is a
                    // query against MySQL's information_schema - which is slow
                    // on a shared server. The answer cannot change mid-request,
                    // so it is remembered for an hour instead of asked for twice
                    // on every page.
                    $tables = Cache::remember('schema:badge_tables', 3600, fn () => [
                        'purchase_batches' => Schema::hasTable('purchase_batches'),
                        'expenses' => Schema::hasTable('expenses'),
                    ]);

                    $alertDays = max(1, (int) Setting::value('supplier_alert_days', '7'));

                    if ($tables['purchase_batches']) {
                        $cacheKey = 'owed_badge:' . ($user->branch_id ?? 'all');
                        $badge = Cache::remember($cacheKey, 60, function () use ($user, $alertDays) {
                            return app(\App\Services\SupplierOwedService::class)
                                ->reminderData($user->branch_id, $alertDays)['badge_count'];
                        });
                    }

                    // Monthly bills due — the count on the Alerts link, so a
                    // reminder is noticed without opening the page.
                    if ($tables['expenses']) {
                        $billsBadge = \App\Models\Expense::dueBillsCached($user->branch_id, $alertDays)->count();
                    }
                } catch (\Throwable $e) {
                    $badge = 0;
                    $billsBadge = 0;
                }
            }
            $view->with('owedBadgeCount', $badge);
            $view->with('billsDueCount', $billsBadge);

            // A lapsed backup is the one risk the app cannot fix on the
            // owner's behalf, so it shows on the sidebar link rather than
            // waiting to be looked for. Only the owner can act on it.
            $view->with(
                'backupStale',
                ($user && $user->role === 'owner_admin') ? BackupHealth::isStale() : false
            );
        });
    }
}
