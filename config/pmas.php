<?php

/**
 * PART D — Branding defaults. These are only fallbacks for a fresh install;
 * the real values live in the settings table (Settings > Business info) so
 * any pharmacy can be branded without touching code. "PMAS" is the generic
 * product name, not a client name.
 */
return [
    'default_business_name' => env('BRANDING_BUSINESS_NAME', 'PMAS'),

    // Fallback palette for a fresh install; the owner can change both on the
    // Business settings page, which feeds the --brand-* CSS variables.
    'default_brand_primary_color' => env('BRANDING_PRIMARY_COLOR', '#12A594'),
    'default_brand_accent_color' => env('BRANDING_ACCENT_COLOR', '#101B2D'),

    // Vendor credited in the app chrome ("by ..." on the login page and
    // "Powered by ..." on every page). Kept in config so it is one edit.
    'vendor_name' => env('VENDOR_NAME', 'AMBA Solutions'),
    'vendor_url' => env('VENDOR_URL', 'https://amba.site.je'),

    /*
    | First-run administrator account, created by ProductionSeeder. There is
    | deliberately no hard-coded password: set ADMIN_PASSWORD before seeding
    | (the seeder prints a random one if you don't) and change it after the
    | first login. Read via config so it still works when config is cached.
    */
    'admin_name' => env('ADMIN_NAME', 'Admin User'),
    'admin_username' => env('ADMIN_USERNAME', 'admin'),
    'admin_email' => env('ADMIN_EMAIL', 'admin@pmas.com'),
    'admin_password' => env('ADMIN_PASSWORD'),

    // Nightly database backups (php artisan pmas:backup): how many to keep.
    'backup_keep' => (int) env('BACKUP_KEEP', 14),

    // Full path to the mysqldump binary when it is not on PATH.
    'mysqldump_path' => env('MYSQLDUMP_PATH'),
];
