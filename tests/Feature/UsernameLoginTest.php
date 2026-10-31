<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff sign in with a username and password. The email is still collected
 * when an account is created but is no longer the login identifier.
 */
class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_asks_for_a_username_not_an_email(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Username')
            ->assertDontSee('Email address');
    }

    public function test_a_user_signs_in_with_their_username(): void
    {
        $user = User::factory()->create([
            'username' => 'pharmacist',
            'email' => 'someone@example.com',
            'password' => 'secret-password',
            'active' => true,
        ]);

        $this->post(route('login.submit'), ['username' => 'pharmacist', 'password' => 'secret-password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_username_login_ignores_the_case_that_was_typed(): void
    {
        $user = User::factory()->create([
            'username' => 'Mixed.Case',
            'password' => 'secret-password',
            'active' => true,
        ]);

        // Stored lower-case by the model...
        $this->assertSame('mixed.case', $user->fresh()->username);

        // ...and the typed value is normalised the same way before matching.
        $this->post(route('login.submit'), ['username' => 'MIXED.CASE', 'password' => 'secret-password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_email_address_no_longer_signs_anyone_in(): void
    {
        User::factory()->create([
            'username' => 'pharmacist',
            'email' => 'someone@example.com',
            'password' => 'secret-password',
            'active' => true,
        ]);

        $this->post(route('login.submit'), ['username' => 'someone@example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_an_inactive_account_cannot_sign_in(): void
    {
        User::factory()->create([
            'username' => 'retired',
            'password' => 'secret-password',
            'active' => false,
        ]);

        $this->post(route('login.submit'), ['username' => 'retired', 'password' => 'secret-password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }
}
