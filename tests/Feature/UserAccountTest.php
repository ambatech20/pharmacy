<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff account management: the owner now sets a username (the login
 * identifier) as well as the email kept on the account.
 */
class UserAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER_ADMIN, 'branch_id' => null]);
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Aster Bekele',
            'username' => 'aster',
            'email' => 'aster@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_PHARMACIST_CLERK,
            'branch_id' => Branch::create(['name' => 'Main Branch'])->id,
        ], $overrides);
    }

    public function test_an_owner_creates_a_staff_account_with_a_username(): void
    {
        $this->actingAs($this->owner())
            ->post(route('users.store'), $this->payload(['username' => 'Aster.T']))
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'aster@example.com')->firstOrFail();

        // Stored lower-case so login matching is predictable.
        $this->assertSame('aster.t', $user->username);
    }

    public function test_a_username_is_required_to_create_a_user(): void
    {
        $this->actingAs($this->owner())
            ->post(route('users.store'), $this->payload(['username' => '']))
            ->assertSessionHasErrors('username');
    }

    public function test_a_duplicate_username_is_rejected_whatever_the_case(): void
    {
        User::factory()->create(['username' => 'taken']);

        $this->actingAs($this->owner())
            ->post(route('users.store'), $this->payload(['username' => 'TAKEN']))
            ->assertSessionHasErrors('username');
    }

    public function test_a_username_with_spaces_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->post(route('users.store'), $this->payload(['username' => 'aster b']))
            ->assertSessionHasErrors('username');
    }

    public function test_the_users_list_shows_the_username_and_the_email(): void
    {
        User::factory()->create([
            'name' => 'Aster Bekele',
            'username' => 'aster',
            'email' => 'aster@example.com',
        ]);

        $this->actingAs($this->owner())
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Username')
            ->assertSee('aster')
            ->assertSee('aster@example.com');
    }
}
