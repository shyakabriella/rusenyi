<?php

namespace Tests\Feature\API\CoffeeSeason;

use App\Models\CoffeeSeason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoffeeSeasonApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_admin_can_create_coffee_season_with_backend_generated_code(): void
    {
        $admin = User::factory()
            ->admin()
            ->create([
                'must_change_password' => false,
            ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson(
            '/api/admin/coffee-seasons',
            [
                'name' => '2026 Main Coffee Season',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'description' => 'Main 2026 coffee season.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                CoffeeSeason::STATUS_DRAFT
            )
            ->assertJsonPath(
                'data.code',
                'CS-2026-001'
            );

        $this->assertDatabaseHas(
            'coffee_seasons',
            [
                'name' => '2026 Main Coffee Season',
                'code' => 'CS-2026-001',
                'status' => CoffeeSeason::STATUS_DRAFT,
                'created_by' => $admin->id,
            ]
        );
    }

    public function test_backend_generates_unique_codes_for_seasons_in_same_year(): void
    {
        $admin = User::factory()
            ->admin()
            ->create([
                'must_change_password' => false,
            ]);

        Sanctum::actingAs($admin);

        $first = $this->postJson(
            '/api/admin/coffee-seasons',
            [
                'name' => '2026 Main Season',
                'start_date' => '2026-01-01',
            ]
        );

        $first
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                'CS-2026-001'
            );

        $second = $this->postJson(
            '/api/admin/coffee-seasons',
            [
                'name' => '2026 Secondary Season',
                'start_date' => '2026-07-01',
            ]
        );

        $second
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                'CS-2026-002'
            );

        $this->assertDatabaseHas(
            'coffee_seasons',
            [
                'code' => 'CS-2026-001',
            ]
        );

        $this->assertDatabaseHas(
            'coffee_seasons',
            [
                'code' => 'CS-2026-002',
            ]
        );
    }

    public function test_admin_can_activate_draft_season(): void
    {
        $admin = User::factory()
            ->admin()
            ->create([
                'must_change_password' => false,
            ]);

        $season = $this->season(
            $admin
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/coffee-seasons/{$season->id}/activate"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                CoffeeSeason::STATUS_ACTIVE
            )
            ->assertJsonPath(
                'data.is_active',
                true
            );

        $this->assertDatabaseHas(
            'coffee_seasons',
            [
                'id' => $season->id,
                'status' => CoffeeSeason::STATUS_ACTIVE,
                'activated_by' => $admin->id,
            ]
        );
    }

    public function test_only_one_coffee_season_can_be_active(): void
    {
        $admin = User::factory()
            ->admin()
            ->create([
                'must_change_password' => false,
            ]);

        CoffeeSeason::create([
            'name' => '2025 Season',
            'code' => 'CS-2025-001',
            'start_date' => '2025-01-01',
            'status' => CoffeeSeason::STATUS_ACTIVE,
            'created_by' => $admin->id,
            'activated_by' => $admin->id,
            'activated_at' => now(),
        ]);

        $second = CoffeeSeason::create([
            'name' => '2026 Season',
            'code' => 'CS-2026-001',
            'start_date' => '2026-01-01',
            'status' => CoffeeSeason::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/coffee-seasons/{$second->id}/activate"
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'success',
                false
            );

        $this->assertDatabaseHas(
            'coffee_seasons',
            [
                'id' => $second->id,
                'status' => CoffeeSeason::STATUS_DRAFT,
            ]
        );
    }

    public function test_admin_can_close_active_season(): void
    {
        $admin = User::factory()
            ->admin()
            ->create([
                'must_change_password' => false,
            ]);

        $season = $this->season(
            $admin,
            CoffeeSeason::STATUS_ACTIVE
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/coffee-seasons/{$season->id}/close"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                CoffeeSeason::STATUS_CLOSED
            )
            ->assertJsonPath(
                'data.is_active',
                false
            );

        $this->assertDatabaseHas(
            'coffee_seasons',
            [
                'id' => $season->id,
                'closed_by' => $admin->id,
                'status' => CoffeeSeason::STATUS_CLOSED,
            ]
        );
    }

    public function test_admin_can_reopen_closed_season_when_no_other_active_season_exists(): void
    {
        $admin = User::factory()
            ->admin()
            ->create([
                'must_change_password' => false,
            ]);

        $season = $this->season(
            $admin,
            CoffeeSeason::STATUS_CLOSED
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/coffee-seasons/{$season->id}/reopen"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                CoffeeSeason::STATUS_ACTIVE
            )
            ->assertJsonPath(
                'data.is_active',
                true
            );

        $this->assertDatabaseHas(
            'coffee_seasons',
            [
                'id' => $season->id,
                'status' => CoffeeSeason::STATUS_ACTIVE,
                'activated_by' => $admin->id,
                'closed_by' => null,
                'closed_at' => null,
            ]
        );
    }

    public function test_closed_season_cannot_be_edited(): void
    {
        $admin = User::factory()
            ->admin()
            ->create([
                'must_change_password' => false,
            ]);

        $season = $this->season(
            $admin,
            CoffeeSeason::STATUS_CLOSED
        );

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/coffee-seasons/{$season->id}",
            [
                'name' => 'Changed Season',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'success',
                false
            );

        $this->assertDatabaseHas(
            'coffee_seasons',
            [
                'id' => $season->id,
                'name' => '2026 Main Season',
            ]
        );
    }

    public function test_authenticated_user_can_get_active_coffee_season(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $season = $this->season(
            $admin,
            CoffeeSeason::STATUS_ACTIVE
        );

        $agent = User::factory()
            ->create([
                'role' => User::ROLE_AGENT,
                'must_change_password' => false,
            ]);

        Sanctum::actingAs($agent);

        $this->getJson(
            '/api/coffee-seasons/active'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $season->id
            )
            ->assertJsonPath(
                'data.code',
                $season->code
            )
            ->assertJsonPath(
                'data.status',
                CoffeeSeason::STATUS_ACTIVE
            );
    }

    public function test_active_endpoint_returns_null_when_no_active_season_exists(): void
    {
        $agent = User::factory()
            ->create([
                'role' => User::ROLE_AGENT,
                'must_change_password' => false,
            ]);

        Sanctum::actingAs($agent);

        $this->getJson(
            '/api/coffee-seasons/active'
        )
            ->assertOk()
            ->assertJsonPath(
                'data',
                null
            );
    }

    public function test_non_admin_cannot_manage_coffee_seasons(): void
    {
        $accountant = User::factory()
            ->accountant()
            ->create([
                'must_change_password' => false,
            ]);

        Sanctum::actingAs(
            $accountant
        );

        $this->postJson(
            '/api/admin/coffee-seasons',
            [
                'name' => '2026 Season',
                'start_date' => '2026-01-01',
            ]
        )->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_get_active_season(): void
    {
        $this->getJson(
            '/api/coffee-seasons/active'
        )->assertUnauthorized();
    }

    public function test_admin_can_search_filter_and_paginate_seasons(): void
    {
        $admin = User::factory()
            ->admin()
            ->create([
                'must_change_password' => false,
            ]);

        CoffeeSeason::create([
            'name' => '2026 Main Season',
            'code' => 'CS-2026-001',
            'start_date' => '2026-01-01',
            'status' => CoffeeSeason::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);

        CoffeeSeason::create([
            'name' => '2025 Main Season',
            'code' => 'CS-2025-001',
            'start_date' => '2025-01-01',
            'status' => CoffeeSeason::STATUS_CLOSED,
            'created_by' => $admin->id,
            'closed_by' => $admin->id,
            'closed_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/admin/coffee-seasons?search=2026&status=draft&per_page=10'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.items.0.code',
                'CS-2026-001'
            );
    }

    private function season(
        User $user,
        string $status = CoffeeSeason::STATUS_DRAFT
    ): CoffeeSeason {
        return CoffeeSeason::create([
            'name' => '2026 Main Season',
            'code' => 'CS-2026-001',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => $status,
            'created_by' => $user->id,

            'activated_by' =>
                $status === CoffeeSeason::STATUS_ACTIVE
                    ? $user->id
                    : null,

            'activated_at' =>
                $status === CoffeeSeason::STATUS_ACTIVE
                    ? now()
                    : null,

            'closed_by' =>
                $status === CoffeeSeason::STATUS_CLOSED
                    ? $user->id
                    : null,

            'closed_at' =>
                $status === CoffeeSeason::STATUS_CLOSED
                    ? now()
                    : null,
        ]);
    }

    private function createRoles(): void
    {
        $roles = [
            ['admin', 'Admin'],
            ['accountant', 'Accountant'],
            ['balance', 'Balance Officer'],
            ['agent', 'Agent'],
            ['driver', 'Driver'],
            ['store', 'Store Officer'],
        ];

        foreach (
            $roles as [$name, $displayName]
        ) {
            DB::table('roles')
                ->insert([
                    'name' => $name,
                    'display_name' => $displayName,
                    'description' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }
}
