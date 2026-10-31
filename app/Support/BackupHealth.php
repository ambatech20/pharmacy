<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;

/**
 * How old the newest backup is, and whether that is old enough to worry about.
 *
 * Lives here rather than in a controller because two screens ask the same
 * question - the dashboard (a banner you cannot miss on the way in) and the
 * Backups page itself. If they derived it separately the sidebar could
 * disagree with the page the sidebar links to.
 *
 * Deliberately measures only backups taken from the Backups screen. A backup
 * pulled through the hosting panel is invisible to the application, so the
 * wording never claims "you have no backup" - only that this screen has not
 * been used.
 */
class BackupHealth
{
    /**
     * A pharmacy logs in every day; two weeks of radio silence means the habit
     * has lapsed. Long enough not to nag, short enough to still be recent if
     * something goes wrong.
     */
    public const STALE_AFTER_DAYS = 14;

    public static function lastTakenAt(): ?Carbon
    {
        $value = Setting::value('last_backup_at');

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            // A hand-edited or corrupted value must not break every page that
            // asks about backup age.
            return null;
        }
    }

    /**
     * Whole days since the last backup, or null when there has never been one.
     * Never negative: a clock that disagrees with the stored value reads as
     * "taken today", which is the harmless direction to be wrong in.
     */
    public static function ageInDays(): ?int
    {
        $last = static::lastTakenAt();

        if (! $last) {
            return null;
        }

        // Carbon 3 returns a signed float unless told otherwise. The sign is
        // what matters: a stored value in the future (a clock that disagrees,
        // or a hand-edited setting) has to read as "just taken" rather than as
        // an enormous age that would nag forever.
        $days = (int) floor($last->startOfDay()->diffInDays(now()->startOfDay(), false));

        return max(0, $days);
    }

    public static function isStale(): bool
    {
        $age = static::ageInDays();

        return $age === null || $age >= self::STALE_AFTER_DAYS;
    }

    /** Plain-language sentence for the banner, or null when all is well. */
    public static function message(): ?string
    {
        $age = static::ageInDays();

        if ($age === null) {
            return 'No backup has been taken from the Backups screen yet. If the database is lost, '
                . 'the medicines, sales and accounts go with it.';
        }

        if ($age >= self::STALE_AFTER_DAYS) {
            return 'The last backup was ' . $age . ' days ago. Take a fresh one - it takes a few seconds.';
        }

        return null;
    }
}
