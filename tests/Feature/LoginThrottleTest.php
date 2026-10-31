<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The login routes carry a per-IP throttle:5,1. On top of that the
     * controller limits failures per credential+IP, which is what stops an
     * attack that rotates source IPs against a single account. The route
     * throttle is removed here so the controller's limiter is exercised on its
     * own: the sixth failure is rejected before it even tries the credentials.
     */
    public function test_login_is_throttled_per_credential(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        $payload = ['username' => 'attacker', 'password' => 'wrong-password'];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.submit'), $payload)->assertSessionHasErrors('username');
        }

        $this->post(route('login.submit'), $payload)->assertSessionHasErrors('username');

        $errors = app('session.store')->get('errors');
        $this->assertStringContainsString(
            'Too many login attempts',
            $errors->getBag('default')->first('username')
        );
    }

    public function test_valid_credentials_still_log_in(): void
    {
        $user = User::factory()->create([
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('secret-password'),
            'active' => true,
        ]);

        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'secret-password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }
}
