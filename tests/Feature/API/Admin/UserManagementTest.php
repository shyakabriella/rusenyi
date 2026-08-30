<?php

namespace Tests\Feature\API\Admin;

use App\Models\User;
use App\Notifications\NewUserCredentialsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    /**
     * Admin can list users.
     */
    public function test_admin_can_list_users(): void
    {
        $admin = $this->admin();

        User::factory()
            ->count(3)
            ->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonStructure([
                'success',
                'message',

                'data' => [
                    'users',

                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                        'from',
                        'to',
                    ],
                ],
            ]);
    }

    /**
     * Admin can create user without password.
     */
    public function test_admin_can_create_user_without_password(): void
    {
        Notification::fake();

        $admin = $this->admin();

        Sanctum::actingAs($admin);

        $response = $this->postJson(
            '/api/admin/users',
            [
                'name' => 'Jean Bosco',

                'email' =>
                    'jean.bosco@gihombo.rw',

                'phone' =>
                    '0788123456',

                'role' =>
                    'agent',
            ]
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.user.name',
                'Jean Bosco'
            )
            ->assertJsonPath(
                'data.user.role',
                'agent'
            );

        $this->assertDatabaseHas(
            'users',
            [
                'email' =>
                    'jean.bosco@gihombo.rw',

                'role' =>
                    'agent',

                'status' =>
                    'active',

                'is_active' =>
                    true,

                'must_change_password' =>
                    true,
            ]
        );

        $createdUser = User::where(
            'email',
            'jean.bosco@gihombo.rw'
        )->firstOrFail();

        Notification::assertSentTo(
            $createdUser,
            NewUserCredentialsNotification::class
        );
    }

    /**
     * Duplicate email is rejected.
     */
    public function test_duplicate_email_is_rejected(): void
    {
        $admin = $this->admin();

        User::factory()->create([
            'email' => 'jean@gihombo.rw',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/admin/users',
            [
                'name' =>
                    'Another Jean',

                'email' =>
                    'jean@gihombo.rw',

                'phone' =>
                    '0788555555',

                'role' =>
                    'agent',
            ]
        )
            ->assertStatus(422);
    }

    /**
     * Duplicate phone is rejected.
     */
    public function test_duplicate_phone_is_rejected(): void
    {
        $admin = $this->admin();

        User::factory()->create([
            'phone' => '0788123456',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/admin/users',
            [
                'name' =>
                    'Another User',

                'email' =>
                    'another@gihombo.rw',

                'phone' =>
                    '0788123456',

                'role' =>
                    'driver',
            ]
        )
            ->assertStatus(422);
    }

    /**
     * Admin can view one user.
     */
    public function test_admin_can_view_user(): void
    {
        $admin = $this->admin();

        $user = User::factory()
            ->agent()
            ->create([
                'name' => 'Jean Bosco',
            ]);

        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/admin/users/{$user->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $user->id
            )
            ->assertJsonPath(
                'data.name',
                'Jean Bosco'
            );
    }

    /**
     * Admin can update user.
     */
    public function test_admin_can_update_user(): void
    {
        $admin = $this->admin();

        $user = User::factory()
            ->agent()
            ->create();

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/users/{$user->id}",
            [
                'name' =>
                    'Updated Agent Name',

                'phone' =>
                    '0788999999',

                'role' =>
                    'agent',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Updated Agent Name'
            );

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,

                'name' =>
                    'Updated Agent Name',

                'phone' =>
                    '0788999999',
            ]
        );
    }

    /**
     * Admin cannot remove own Admin role.
     */
    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = $this->admin();

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/users/{$admin->id}",
            [
                'role' => 'agent',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'success',
                false
            );

        $this->assertDatabaseHas(
            'users',
            [
                'id' =>
                    $admin->id,

                'role' =>
                    'admin',
            ]
        );
    }

    /**
     * Admin can suspend user.
     */
    public function test_admin_can_suspend_user(): void
    {
        $admin = $this->admin();

        $user = User::factory()
            ->agent()
            ->create();

        $user->createToken(
            'mobile-app'
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/users/{$user->id}/status",
            [
                'status' =>
                    'suspended',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'suspended'
            )
            ->assertJsonPath(
                'data.is_active',
                false
            );

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'tokenable_id' =>
                    $user->id,
            ]
        );
    }

    /**
     * Admin can reactivate user.
     */
    public function test_admin_can_reactivate_user(): void
    {
        $admin = $this->admin();

        $user = User::factory()
            ->suspended()
            ->create();

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/users/{$user->id}/status",
            [
                'status' => 'active',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'active'
            )
            ->assertJsonPath(
                'data.is_active',
                true
            );
    }

    /**
     * Admin cannot suspend own account.
     */
    public function test_admin_cannot_suspend_own_account(): void
    {
        $admin = $this->admin();

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/users/{$admin->id}/status",
            [
                'status' =>
                    'suspended',
            ]
        )
            ->assertStatus(422);
    }

    /**
     * Non-admin cannot access User Management.
     */
    public function test_non_admin_cannot_access_user_management(): void
    {
        $agent = User::factory()
            ->agent()
            ->create([
                'must_change_password' =>
                    false,
            ]);

        Sanctum::actingAs($agent);

        $this->getJson(
            '/api/admin/users'
        )
            ->assertStatus(403);
    }

    /**
     * Filter users by role.
     */
    public function test_admin_can_filter_users_by_role(): void
    {
        $admin = $this->admin();

        User::factory()
            ->agent()
            ->count(2)
            ->create();

        User::factory()
            ->driver()
            ->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/users?role=agent'
        );

        $response->assertOk();

        foreach (
            $response->json(
                'data.users'
            ) as $user
        ) {
            $this->assertSame(
                'agent',
                $user['role']
            );
        }
    }

    /**
     * Filter users by status.
     */
    public function test_admin_can_filter_users_by_status(): void
    {
        $admin = $this->admin();

        User::factory()
            ->agent()
            ->create();

        User::factory()
            ->suspended()
            ->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/users?status=suspended'
        );

        $response->assertOk();

        foreach (
            $response->json(
                'data.users'
            ) as $user
        ) {
            $this->assertSame(
                'suspended',
                $user['status']
            );
        }
    }

    /**
     * Search users.
     */
    public function test_admin_can_search_users(): void
    {
        $admin = $this->admin();

        User::factory()
            ->agent()
            ->create([
                'name' =>
                    'Jean Bosco',

                'email' =>
                    'jean.bosco@gihombo.rw',
            ]);

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/admin/users?search=Jean'
        )
            ->assertOk()
            ->assertJsonFragment([
                'name' =>
                    'Jean Bosco',
            ]);
    }

    /**
     * Pagination works.
     */
    public function test_user_list_is_paginated(): void
    {
        $admin = $this->admin();

        User::factory()
            ->count(25)
            ->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/users?per_page=10'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.per_page',
                10
            );

        $this->assertCount(
            10,
            $response->json(
                'data.users'
            )
        );
    }

    /**
     * Admin can reset credentials.
     */
    public function test_admin_can_reset_user_credentials(): void
    {
        Notification::fake();

        $admin = $this->admin();

        $user = User::factory()
            ->agent()
            ->create([
                'must_change_password' =>
                    false,
            ]);

        $token = $user->createToken(
            'mobile-app'
        );

        $oldPassword =
            $user->password;

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/users/{$user->id}/reset-credentials"
        )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.must_change_password',
                true
            );

        $user->refresh();

        /*
         * Password hash must change.
         */
        $this->assertNotSame(
            $oldPassword,
            $user->password
        );

        /*
         * User must change password on next login.
         */
        $this->assertTrue(
            $user->must_change_password
        );

        /*
         * Existing login tokens are revoked.
         */
        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'id' =>
                    $token->accessToken->id,
            ]
        );

        Notification::assertSentTo(
            $user,
            NewUserCredentialsNotification::class
        );
    }

    /**
     * Admin can resend credentials.
     */
    public function test_admin_can_resend_user_credentials(): void
    {
        Notification::fake();

        $admin = $this->admin();

        $user = User::factory()
            ->agent()
            ->create([
                'must_change_password' =>
                    false,
            ]);

        $oldPassword =
            $user->password;

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/users/{$user->id}/resend-credentials"
        )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            );

        $user->refresh();

        $this->assertNotSame(
            $oldPassword,
            $user->password
        );

        $this->assertTrue(
            $user->must_change_password
        );

        Notification::assertSentTo(
            $user,
            NewUserCredentialsNotification::class
        );
    }

    /**
     * Cannot reset credentials for suspended user.
     */
    public function test_cannot_reset_credentials_for_suspended_user(): void
    {
        $admin = $this->admin();

        $user = User::factory()
            ->suspended()
            ->create();

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/users/{$user->id}/reset-credentials"
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'success',
                false
            );
    }

    /**
     * Create authenticated Admin.
     */
    private function admin(): User
    {
        return User::factory()
            ->admin()
            ->create([
                'must_change_password' =>
                    false,
            ]);
    }

    /**
     * Create required system roles.
     */
    private function createRoles(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'display_name' => 'Admin',
            ],

            [
                'name' => 'accountant',
                'display_name' => 'Accountant',
            ],

            [
                'name' => 'balance',
                'display_name' => 'Balance Officer',
            ],

            [
                'name' => 'agent',
                'display_name' => 'Agent',
            ],

            [
                'name' => 'driver',
                'display_name' => 'Driver',
            ],

            [
                'name' => 'store',
                'display_name' => 'Store Officer',
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insert([
                'name' =>
                    $role['name'],

                'display_name' =>
                    $role['display_name'],

                'description' =>
                    null,

                'is_active' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
        }
    }
}
