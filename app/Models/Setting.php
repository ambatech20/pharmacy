<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Simple key/value settings store (Part B reminder window now, Part D
 * branding later). Global by convention; branch-specific values can use a
 * "branch:{id}:{key}" style name if ever needed. Reads are cached so the
 * sidebar badge / layout don't hit the DB repeatedly.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * The only windows the expiring-stock alert may use. Keeping this a fixed
     * list means a fat-fingered value can never widen the alert (hiding
     * short-dated stock) or narrow it to nothing.
     */
    public const EXPIRY_ALERT_WINDOWS = [30, 60, 90];

    public static function value(string $key, $default = null)
    {
        $all = static::allCached();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function allCached(): array
    {
        return Cache::remember('settings.all', 300, function () {
            try {
                return static::query()->pluck('value', 'key')->all();
            } catch (\Throwable $e) {
                // Table may not exist yet during first migration run.
                return [];
            }
        });
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }

    /**
     * The VAT percentage already contained in the prices the pharmacy charges
     * (15 in Ethiopia). 0 means the owner has not set a rate, so receipts show
     * no breakdown rather than inventing one.
     */
    public static function vatRatePercent(): float
    {
        $rate = round((float) (string) static::value('vat_rate_percent', 0), 2);

        return ($rate > 0 && $rate < 100) ? $rate : 0.0;
    }

    /**
     * How many days ahead the "expiring soon" alert looks. Only 30, 60 or 90
     * are offered; anything else falls back to the 90-day default so a bad
     * stored value can never widen or hide the alert. Pass $requested to
     * resolve a one-off value from a query string instead.
     */
    public static function expiryAlertDays($requested = null): int
    {
        if ($requested !== null && in_array((int) $requested, self::EXPIRY_ALERT_WINDOWS, true)) {
            return (int) $requested;
        }

        $stored = (int) static::value('expiry_alert_days', 90);

        return in_array($stored, self::EXPIRY_ALERT_WINDOWS, true) ? $stored : 90;
    }

    /**
     * The stored branding logo path, or null. Guards against a stored path
     * whose file is gone (restored database, cleaned storage) so the layout
     * falls back to the initials badge instead of a broken image.
     */
    public static function logoPath(?string $path = null): ?string
    {
        $path = $path ?? static::value('logo_path');

        return ($path && Storage::disk('public')->exists($path)) ? $path : null;
    }
}
