<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Is this install actually safe to put on the internet?
 *
 * Deployment runbooks get skimmed. This command is the short version: it reads
 * the live configuration and refuses to call the install ready when something
 * that matters is wrong. Every check reads from config(), never env(), so it
 * still works when the configuration is cached - env() returns null there and
 * a check built on it would quietly pass everything.
 *
 * Exit code 0 means nothing failed. Warnings do not fail the run; failures do.
 */
class PreflightCheck extends Command
{
    protected $signature = 'pmas:preflight
        {--json : Output machine-readable results instead of a readable report}
        {--skip-dns : Do not attempt DNS resolution (use on a machine with no network)}';

    protected $description = 'Check this install is ready to be exposed on the internet';

    /**
     * SHA-256 of a known-leaked APP_KEY. The value itself lives in
     * config/instance.php so it can be substituted in tests; only the
     * comparison happens here.
     */
    protected function leakedKeyFingerprint(): string
    {
        return (string) config('instance.leaked_key_fingerprint');
    }

    /** @var array<int, array{check: string, state: string, detail: string}> */
    protected array $results = [];

    public function handle(): int
    {
        $this->checkEnvironment();
        $this->checkDomain();
        $this->checkCookies();
        $this->checkStorage();
        $this->checkDatabase();
        $this->checkCaches();
        $this->checkBackups();

        $failed = $this->failureCount();
        $warned = count(array_filter($this->results, fn ($r) => $r['state'] === 'warn'));

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ready' => $failed === 0,
                'failed' => $failed,
                'warned' => $warned,
                'results' => $this->results,
            ], JSON_PRETTY_PRINT));

            return $failed > 0 ? 1 : 0;
        }

        $this->newLine();

        if ($failed > 0) {
            $this->error("NOT READY - {$failed} blocking problem(s). Fix every FAIL line before this host is reachable by anyone.");
        } elseif ($warned > 0) {
            $this->warn("Ready, with {$warned} warning(s) worth reading.");
        } else {
            $this->info('Ready. Nothing blocking found.');
        }

        return $failed > 0 ? 1 : 0;
    }

    // ---------------------------------------------------------------- checks

    protected function checkEnvironment(): void
    {
        $env = (string) config('app.env');
        $this->check(
            'APP_ENV is production',
            $env === 'production',
            $env,
            $env === 'production'
                ? null
                : 'Set APP_ENV=production. An environment mismatch is the most common way a debug stack ends up on the internet, and it also decides whether SecurityHeaders enforces HTTPS and sends HSTS.'
        );

        $debug = (bool) config('app.debug');
        $this->check(
            'APP_DEBUG is off',
            ! $debug,
            $debug ? 'on' : 'off',
            $debug ? 'Set APP_DEBUG=false. Debug mode prints stack traces, file paths and configuration to anyone who triggers an error.' : null
        );

        $key = (string) config('app.key');
        $this->check(
            'APP_KEY is set',
            $key !== '' && $key !== 'base64:',
            $key === '' ? 'empty' : 'set',
            'Run: php artisan key:generate --force'
        );

        // The key that was committed before .env was untracked. Anyone who can
        // read the repository can decrypt sessions and forge signed URLs, so it
        // must be treated as public and must not still be in use. The expected
        // value is a fingerprint, read from config so it can be substituted in
        // tests - see config/instance.php.
        $fingerprint = $this->leakedKeyFingerprint();

        $this->check(
            'APP_KEY is not the one leaked to git',
            $key === '' || $fingerprint === '' || hash('sha256', $key) !== $fingerprint,
            $key === '' ? 'n/a' : 'set',
            'This APP_KEY was committed to the repository before .env was untracked, so it must be treated as public. Run: php artisan key:generate --force (it signs everyone out, which is the point)'
        );

        $timezone = (string) config('app.timezone');
        $this->check(
            'APP_TIMEZONE is set',
            $timezone !== 'UTC',
            $timezone,
            $timezone === 'UTC' ? 'Set APP_TIMEZONE=Africa/Addis_Ababa. On UTC, "due today" and the till day-end cut-off are three hours out.' : null
        );
    }

    protected function checkDomain(): void
    {
        $url = (string) config('app.url');
        $https = str_starts_with($url, 'https://');

        $this->check(
            'APP_URL is https',
            $https,
            $url,
            $https ? null : 'Set APP_URL=https://... Sessions and the admin password travel in clear text over http://.'
        );

        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        $this->check(
            'APP_URL has a real host name',
            $host !== '' && $host !== 'localhost' && $host !== '127.0.0.1',
            $host === '' ? 'none' : $host,
            in_array($host, ['localhost', '127.0.0.1'], true)
                ? 'APP_URL still points at localhost, so generated links and redirects will send staff off-site.'
                : null
        );

        // Only meaningful on the real host. On a developer machine APP_URL is
        // always pmas.test and TRUSTED domain never matches, so running it here
        // would be a guaranteed false alarm that trains people to ignore it.
        $expected = trim((string) config('instance.domain'));

        if ($expected !== '' && $this->laravel->environment('production')) {
            $this->check(
                "APP_URL host matches INSTANCE_DOMAIN ({$expected})",
                $host === $expected,
                $host,
                $host === $expected
                    ? null
                    : 'INSTANCE_DOMAIN only drives this check, so a mismatch usually means APP_URL was edited on its own.'
            );
        }

        if ($this->option('skip-dns') || $host === '' || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return;
        }

        // Advisory. The box may have no outbound DNS, and an install on a
        // private network reached by IP is a legitimate setup.
        $resolved = @gethostbyname($host);

        if ($resolved === $host || $resolved === '') {
            $this->warnCheck(
                "DNS resolves {$host}",
                'does not resolve from this machine',
                'Staff on the internet will not reach the site until an A record exists for this name.'
            );
        } else {
            $this->passCheck("DNS resolves {$host}", $resolved);
        }
    }

    protected function checkCookies(): void
    {
        $secure = (bool) config('session.secure');
        $this->check(
            'Session cookie is secure-only',
            $secure,
            $secure ? 'yes' : 'no',
            $secure ? null : 'Set SESSION_SECURE_COOKIE=true, otherwise the session cookie is sent over plain http and can be taken on a shared network.'
        );

        $this->check(
            'Session cookie is http-only',
            (bool) config('session.http_only'),
            config('session.http_only') ? 'yes' : 'no',
            null
        );

        $domain = config('session.domain');
        $this->check(
            'Session cookie domain is sane',
            $domain === null || $domain === '' || ! str_contains((string) $domain, 'localhost'),
            $domain === null || $domain === '' ? 'host-only (fine)' : (string) $domain,
            str_contains((string) $domain, 'localhost')
                ? 'SESSION_DOMAIN still points at localhost, which will stop every login working.'
                : null
        );

        $proxies = trim((string) config('instance.trusted_proxies'));
        $this->check(
            'TRUSTED_PROXIES is configured',
            $proxies !== '',
            $proxies === '' ? 'blank' : $proxies,
            $proxies === ''
                ? 'If anything sits in front of PHP, leaving this blank makes every request look like plain http, so HTTPS enforcement and client IPs are both wrong. Set the proxy IP, or "*" only if you control the whole path.'
                : null
        );

        // Whether the HTTPS redirect will actually fire is decided by
        // APP_ENV, which the environment check above already covers and
        // explains. Repeating it here could only ever agree with it, and in a
        // test suite it would report on the test environment rather than on
        // the install being deployed.
    }

    protected function checkStorage(): void
    {
        $paths = [
            'storage/ is writable' => storage_path(),
            'bootstrap/cache is writable' => $this->laravel->bootstrapPath('cache'),
        ];

        foreach ($paths as $label => $path) {
            // Same reasoning as the storage link below: stat results are
            // cached, and a stale entry here would fail a healthy install.
            clearstatcache(true, $path);
            $writable = is_dir($path) && is_writable($path);
            $this->check(
                $label,
                $writable,
                $writable ? 'yes' : 'not writable',
                $writable ? null : "PHP cannot write to {$path}. Sessions, logs and cached config all fail without it."
            );
        }

        // Branding images are served through public/storage, which is a
        // symlink into storage/app/public. Without it the pharmacy's logo is a
        // broken image on the login screen and in the header of every page -
        // so this blocks rather than warns. Hosts that refuse symlinks are the
        // reason this check is loud: they need to be told before opening, not
        // after someone reports the logo.
        //
        // Deliberately file_exists() + is_readable() rather than is_link() or
        // is_dir(). is_dir() on a directory symlink is not dependable - it
        // depends on stat-cache state and call order, and was observed
        // returning true and false for the same untouched path in one run.
        // file_exists() follows the link and answers the question that
        // actually matters: can the web server read through it?
        $link = public_path('storage');
        $present = file_exists($link) && is_readable($link);

        $this->check(
            'Public storage link exists (pharmacy logo)',
            $present,
            $present ? 'readable' : 'missing or unreadable',
            'Run: php artisan storage:link. If the host refuses symlinks, ask support to copy storage/app/public into public/storage, or to enable the public/storage disk.'
        );
    }

    protected function checkDatabase(): void
    {
        $connection = config('database.default');

        try {
            DB::connection()->getPdo();
            $this->passCheck('Database is reachable', (string) config("database.connections.{$connection}.database"));
        } catch (\Throwable $e) {
            $this->failCheck('Database is reachable', $e->getMessage());

            return;
        }

        try {
            // Read the directory directly. The Migrator is handed its paths as
            // an argument to run() and keeps no list to read back, so asking it
            // for them throws - and the catch below would quietly downgrade a
            // real check to a warning.
            $files = glob(database_path('migrations/*.php')) ?: [];
            $onDisk = array_map(fn ($file) => basename($file, '.php'), $files);
            $ran = app('migrator')->getRepository()->getRan();
            $outstanding = array_values(array_diff($onDisk, $ran));

            $this->check(
                'No pending migrations',
                $outstanding === [],
                $outstanding === [] ? 'none' : count($outstanding) . ' pending: ' . implode(', ', array_slice($outstanding, 0, 3)),
                $outstanding === [] ? null : 'Run: php artisan migrate --force'
            );
        } catch (\Throwable $e) {
            $this->warnCheck('Migrations checked', 'could not be read: ' . $e->getMessage());
        }
    }

    protected function checkCaches(): void
    {
        $built = [
            'configuration:cache is built' => base_path('bootstrap/cache/config.php'),
            'route:cache is built' => base_path('bootstrap/cache/routes-v7.php'),
        ];

        foreach ($built as $label => $file) {
            $exists = File::exists($file);
            $this->warnCheck(
                $label,
                $exists,
                $exists ? 'yes' : 'not built',
                'Rebuild everything with: php artisan optimize'
            );
        }
    }

    protected function checkBackups(): void
    {
        $dir = storage_path('app/private/backups');

        if (! is_dir($dir)) {
            $this->warnCheck('Backup directory', 'missing: ' . $dir, 'php artisan pmas:backup creates it on first run.');

            return;
        }

        $files = glob($dir . '/*.sql*') ?: [];
        $newest = null;

        foreach ($files as $file) {
            $time = filemtime($file);
            if ($time !== false && ($newest === null || $time > $newest)) {
                $newest = $time;
            }
        }

        $this->warnCheck(
            'A database backup exists',
            $files !== [],
            $files === []
                ? 'none in ' . $dir
                : count($files) . ' file(s), newest ' . date('Y-m-d H:i', (int) $newest),
            'Confirm the nightly job runs, and that a backup has been restored onto a scratch database. An untested backup is not a backup.'
        );
    }

    // --------------------------------------------------------------- helpers

    /** Record a check. $fix is shown only when the check fails. */
    protected function check(string $check, bool $passed, string $detail, ?string $fix = null): void
    {
        $this->results[] = ['check' => $check, 'state' => $passed ? 'pass' : 'fail', 'detail' => $detail];

        if ($this->option('json')) {
            return;
        }

        $mark = $passed ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
        $this->line("  {$mark}  {$check} <fg=gray>({$detail})</>");

        if (! $passed && $fix !== null) {
            $this->line("        <fg=yellow>{$fix}</>");
        }
    }

    protected function passCheck(string $check, string $detail): void
    {
        $this->check($check, true, $detail);
    }

    protected function failCheck(string $check, string $detail): void
    {
        $this->check($check, false, $detail, 'Check DB_* in .env and that the database server is reachable.');
    }

    protected function warnCheck(string $check, bool $passed, string $detail, ?string $advice = null): void
    {
        if ($passed) {
            $this->passCheck($check, $detail);

            return;
        }

        $this->results[] = ['check' => $check, 'state' => 'warn', 'detail' => $detail];

        if ($this->option('json')) {
            return;
        }

        $this->line("  <fg=yellow>WARN</>  {$check} <fg=gray>({$detail})</>");

        if ($advice !== null) {
            $this->line("        <fg=gray>{$advice}</>");
        }
    }

    protected function failureCount(): int
    {
        return count(array_filter($this->results, fn ($r) => $r['state'] === 'fail'));
    }
}
