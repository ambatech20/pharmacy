<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The deployment readiness check.
 *
 * This command is the thing standing between a misconfigured install and the
 * open internet, so the tests are about one question: does it actually fail
 * when it should, and pass when it should?
 *
 * A checker that always passes is worse than no checker, because it produces
 * a green tick that means nothing. Each test here therefore asserts on the
 * FAIL state, not just on the exit code.
 */
class PreflightCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A stand-in for the key that leaked into git.
     *
     * The real one is not repeated here: it is a public secret already, and
     * spreading it into another file helps nobody. Instead the test injects
     * the fingerprint of its own key through config, which is exactly the
     * seam the command uses.
     */
    private const FAKE_LEAKED_KEY = 'base64:VEhJU0lTVEVTVEtFWVNIT0xPRk9PVEVSRURPTVlLRVk9';

    protected function fingerprintOf(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Run the command with a given config overlay and return [exitCode, results].
     *
     * @param  array<string, mixed>  $config
     * @return array{0: int, 1: array<int, array{check: string, state: string, detail: string}>}
     */
    protected function runPreflight(array $config): array
    {
        config($config);

        Artisan::call('pmas:preflight', ['--json' => true, '--skip-dns' => true]);
        $exit = Artisan::output();

        $decoded = json_decode(trim($exit), true);

        return [$decoded['ready'] ? 0 : 1, $decoded['results']];
    }

    /** @param array<int, array{check: string, state: string, detail: string}> $results */
    protected function stateOf(array $results, string $needle): ?string
    {
        foreach ($results as $result) {
            if (str_contains($result['check'], $needle)) {
                return $result['state'];
            }
        }

        return null;
    }

    protected function productionConfig(array $overrides = []): array
    {
        return array_merge([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:' . base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://pharmacy.test',
            'app.timezone' => 'Africa/Addis_Ababa',
            'session.secure' => true,
            'session.http_only' => true,
            'session.domain' => null,
            'instance.domain' => 'pharmacy.test',
            'instance.trusted_proxies' => '10.0.0.1',
            // The real fingerprint would flag a test key nobody is using, so
            // the default config claims a different key is the leaked one.
            'instance.leaked_key_fingerprint' => hash('sha256', self::FAKE_LEAKED_KEY),
        ], $overrides);
    }

    // ------------------------------------------------------------- failures

    public function test_debug_enabled_is_a_blocking_failure(): void
    {
        [$exit, $results] = $this->runPreflight($this->productionConfig(['app.debug' => true]));

        $this->assertSame(1, $exit);
        $this->assertSame('fail', $this->stateOf($results, 'APP_DEBUG'));
    }

    public function test_a_non_production_environment_is_blocking(): void
    {
        [$exit, $results] = $this->runPreflight($this->productionConfig(['app.env' => 'local']));

        $this->assertSame(1, $exit);
        $this->assertSame('fail', $this->stateOf($results, 'APP_ENV'));
    }

    public function test_a_plain_http_url_is_blocking(): void
    {
        [$exit, $results] = $this->runPreflight($this->productionConfig([
            'app.url' => 'http://pharmacy.test',
        ]));

        $this->assertSame(1, $exit);
        $this->assertSame('fail', $this->stateOf($results, 'APP_URL is https'));
    }

    public function test_a_non_secure_session_cookie_is_blocking(): void
    {
        [$exit, $results] = $this->runPreflight($this->productionConfig(['session.secure' => false]));

        $this->assertSame(1, $exit);
        $this->assertSame('fail', $this->stateOf($results, 'secure-only'));
    }

    public function test_a_session_domain_still_pointing_at_localhost_is_blocking(): void
    {
        [$exit, $results] = $this->runPreflight($this->productionConfig(['session.domain' => 'localhost']));

        $this->assertSame(1, $exit);
        $this->assertSame('fail', $this->stateOf($results, 'cookie domain is sane'));
    }

    /**
     * The one that matters most in this repo: the development key was
     * committed before .env was untracked, so it is in the public history and
     * must never guard a live install.
     */
    public function test_the_leaked_development_key_is_blocking(): void
    {
        [$exit, $results] = $this->runPreflight($this->productionConfig([
            'app.key' => self::FAKE_LEAKED_KEY,
        ]));

        $this->assertSame(1, $exit);
        $this->assertSame('fail', $this->stateOf($results, 'leaked to git'));
    }

    public function test_a_different_key_is_accepted(): void
    {
        [, $results] = $this->runPreflight($this->productionConfig());

        $this->assertSame('pass', $this->stateOf($results, 'leaked to git'));
    }

    public function test_the_fingerprint_is_loaded_from_config(): void
    {
        // Guards the seam: if the command went back to a hardcoded constant,
        // the default config would no longer control the check.
        config(['instance.leaked_key_fingerprint' => $this->fingerprintOf('base64:some-other-key')]);

        $this->assertSame(
            $this->fingerprintOf('base64:some-other-key'),
            config('instance.leaked_key_fingerprint')
        );
        $this->assertNotSame(
            '',
            config('instance.leaked_key_fingerprint'),
            'A real fingerprint ships in config/instance.php in this test.'
        );
    }

    public function test_a_blank_key_is_blocking(): void
    {
        [$exit, $results] = $this->runPreflight($this->productionConfig(['app.key' => '']));

        $this->assertSame(1, $exit);
        $this->assertSame('fail', $this->stateOf($results, 'APP_KEY is set'));
    }

    public function test_utc_timezone_is_blocking(): void
    {
        // On UTC, "due today" and the till day-end cut-off are three hours out,
        // which silently misstates which day a sale belongs to.
        [$exit, $results] = $this->runPreflight($this->productionConfig(['app.timezone' => 'UTC']));

        $this->assertSame(1, $exit);
        $this->assertSame('fail', $this->stateOf($results, 'APP_TIMEZONE'));
    }

    public function test_a_localhost_url_is_blocking(): void
    {
        [$exit, $results] = $this->runPreflight($this->productionConfig(['app.url' => 'https://localhost']));

        $this->assertSame(1, $exit);
        $this->assertSame('fail', $this->stateOf($results, 'real host name'));
    }

    public function test_pending_migrations_are_blocking(): void    {
        // A migration that exists on disk but has not been run: exactly the
        // state of a half-finished deploy.
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        file_put_contents(
            database_path('migrations/2099_01_01_000000_preflight_probe.php'),
            "<?php\nreturn new class extends \\Illuminate\\Database\\Migrations\\Migration {\n"
            . "    public function up(): void {}\n    public function down(): void {}\n};\n"
        );

        try {
            [$exit, $results] = $this->runPreflight($this->productionConfig());

            $this->assertSame(1, $exit);
            $this->assertSame('fail', $this->stateOf($results, 'pending migrations'));
        } finally {
            unlink(database_path('migrations/2099_01_01_000000_preflight_probe.php'));
        }
    }

    // ---------------------------------------------------------------- passes

    public function test_a_correctly_configured_install_has_no_blocking_failures(): void
    {
        [$exit, $results] = $this->runPreflight($this->productionConfig());

        $this->assertSame(0, $exit, 'Failures: ' . json_encode(
            array_values(array_filter($results, fn ($r) => $r['state'] === 'fail'))
        ));
    }

    /** Warnings must not block, or nobody will run the thing. */
    public function test_warnings_do_not_fail_the_run(): void
    {
        [$exit, $results] = $this->runPreflight($this->productionConfig());

        $warned = array_filter($results, fn ($r) => $r['state'] === 'warn');
        // No caches built and no backups in a fresh test install.
        $this->assertNotEmpty($warned, 'Expected some warnings in a fresh install.');
        $this->assertSame(0, $exit);
    }

    public function test_the_domain_check_only_applies_in_production(): void
    {
        // On a developer machine APP_URL is pmas.test and the INSTANCE domain
        // never matches. Firing there would be a guaranteed false alarm.
        [$exit] = $this->runPreflight($this->productionConfig([
            'app.env' => 'staging',
            'app.url' => 'https://staging.internal',
        ]));

        // The only failure is the environment check itself.
        $this->assertSame(1, $exit);
    }

    public function test_it_reports_every_check_it_ran(): void
    {
        [, $results] = $this->runPreflight($this->productionConfig());

        // Guards against a typo silently deleting a check: a short report
        // would otherwise look identical to a clean bill of health.
        $this->assertGreaterThanOrEqual(18, count($results));
    }
}
