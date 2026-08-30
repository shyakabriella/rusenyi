<?php

namespace Tests\Feature\API\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Active user can login.
     */
    public function test_active_user_can_login(): void
    {
        $user = User::factory()
            ->admin()
            ->create([
                'email' => 'admin@gihombo.rw',
                'password' => 'Gihombo@123',
                'must_change_password' => false,
            ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@gihombo.rw',
            'password' => 'Gihombo@123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.user.id',
                $user->id
            )
            ->assertJsonPath(
                'data.user.role',
                'admin'
            )
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'role',
                        'status',
                        'must_change_password',
                    ],
                ],
            ]);

        $this->assertNotNull(
            $user->fresh()->last_login_at
        );
    }

    /**
     * Login fails when password is incorrect.
     */
    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()
            ->admin()
            ->create([
                'email' => 'admin@gihombo.rw',
                'password' => 'Gihombo@123',
            ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@gihombo.rw',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(401)
            ->assertJsonPath(
                'success',
                false
            );
    }

    /**
     * Inactive user cannot login.
     */
    public function test_inactive_user_cannot_login(): void
    {
        User::factory()
            ->inactive()
            ->create([
                'email' => 'inactive@gihombo.rw',
                'password' => 'Gihombo@123',
            ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@gihombo.rw',
            'password' => 'Gihombo@123',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath(
                'success',
                false
            );
    }

    /**
     * Suspended user cannot login.
     */
    public function test_suspended_user_cannot_login(): void
    {
        User::factory()
            ->suspended()
            ->create([
                'email' => 'suspended@gihombo.rw',
                'password' => 'Gihombo@123',
            ]);

        $response = $this->postJson('/api/login', [
            'email' => 'suspended@gihombo.rw',
            'password' => 'Gihombo@123',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath(
                'success',
                false
            );
    }

    /**
     * /me returns the exact authenticated user.
     *
     * This is important because multiple users
     * can have the same role.
     */
    public function test_me_returns_exact_authenticated_user(): void
    {
        $user = User::factory()
            ->agent()
            ->create([
                'name' => 'Jean Bosco',
                'email' => 'jean@gihombo.rw',
                'must_change_password' => false,
            ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.id',
                $user->id
            )
            ->assertJsonPath(
                'data.name',
                'Jean Bosco'
            )
            ->assertJsonPath(
                'data.email',
                'jean@gihombo.rw'
            )
            ->assertJsonPath(
                'data.role',
                'agent'
            );
    }

    /**
     * Authenticated user can logout.
     */
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()
            ->agent()
            ->create([
                'must_change_password' => false,
            ]);

        $token = $user->createToken(
            'test-token'
        );

        $response = $this
            ->withHeader(
                'Authorization',
                'Bearer ' . $token->plainTextToken
            )
            ->postJson('/api/logout');

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    /**
     * Unauthenticated user cannot access /me.
     */
    public function test_unauthenticated_user_cannot_access_me(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401);
    }
}
