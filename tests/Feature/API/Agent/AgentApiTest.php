<?php

namespace Tests\Feature\API\Agent;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_admin_can_create_agent_profile_with_generated_code(): void
    {
        $admin =
            $this->admin();

        $agentUser =
            $this->agentUser();

        $location =
            $this->location('A');

        Sanctum::actingAs(
            $admin
        );

        $this->postJson(
            '/api/admin/agents',
            [
                'user_id' =>
                    $agentUser->id,

                'home_village_id' =>
                    $location[
                        'village_id'
                    ],

                'notes' =>
                    'Rusenyi field agent.',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.agent_code',
                'AGT-000001'
            )
            ->assertJsonPath(
                'data.user.id',
                $agentUser->id
            )
            ->assertJsonPath(
                'data.status',
                Agent::STATUS_ACTIVE
            );

        $this->assertDatabaseHas(
            'agents',
            [
                'agent_code' =>
                    'AGT-000001',

                'user_id' =>
                    $agentUser->id,

                'created_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_backend_generates_sequential_agent_codes(): void
    {
        $admin =
            $this->admin();

        Sanctum::actingAs(
            $admin
        );

        $this->postJson(
            '/api/admin/agents',
            [
                'user_id' =>
                    $this
                        ->agentUser(
                            'Agent One',
                            'agent1@test.com',
                            '0788000011'
                        )
                        ->id,
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.agent_code',
                'AGT-000001'
            );

        $this->postJson(
            '/api/admin/agents',
            [
                'user_id' =>
                    $this
                        ->agentUser(
                            'Agent Two',
                            'agent2@test.com',
                            '0788000012'
                        )
                        ->id,
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.agent_code',
                'AGT-000002'
            );
    }

    public function test_non_agent_user_cannot_get_agent_profile(): void
    {
        $admin =
            $this->admin();

        $accountant =
            User::factory()
                ->accountant()
                ->create([
                    'must_change_password' =>
                        false,
                ]);

        Sanctum::actingAs(
            $admin
        );

        $this->postJson(
            '/api/admin/agents',
            [
                'user_id' =>
                    $accountant->id,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'user_id'
            );
    }

    public function test_same_user_cannot_have_two_agent_profiles(): void
    {
        $admin =
            $this->admin();

        $agentUser =
            $this->agentUser();

        Sanctum::actingAs(
            $admin
        );

        $this->postJson(
            '/api/admin/agents',
            [
                'user_id' =>
                    $agentUser->id,
            ]
        )->assertCreated();

        $this->postJson(
            '/api/admin/agents',
            [
                'user_id' =>
                    $agentUser->id,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'user_id'
            );
    }

    public function test_admin_can_view_agent(): void
    {
        $admin =
            $this->admin();

        $agentUser =
            $this->agentUser();

        $location =
            $this->location('A');

        $agent =
            $this->agent(
                $admin,
                $agentUser,
                $location
            );

        Sanctum::actingAs(
            $admin
        );

        $this->getJson(
            "/api/admin/agents/{$agent->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $agent->id
            )
            ->assertJsonPath(
                'data.user.id',
                $agentUser->id
            )
            ->assertJsonPath(
                'data.home_location.village.id',
                $location[
                    'village_id'
                ]
            );
    }

    public function test_admin_can_update_agent_profile(): void
    {
        $admin =
            $this->admin();

        $agentUser =
            $this->agentUser();

        $first =
            $this->location('A');

        $second =
            $this->location('B');

        $agent =
            $this->agent(
                $admin,
                $agentUser,
                $first
            );

        Sanctum::actingAs(
            $admin
        );

        $this->putJson(
            "/api/admin/agents/{$agent->id}",
            [
                'home_village_id' =>
                    $second[
                        'village_id'
                    ],

                'notes' =>
                    'Updated area.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.home_location.village.id',
                $second[
                    'village_id'
                ]
            )
            ->assertJsonPath(
                'data.agent_code',
                $agent->agent_code
            );

        $this->assertDatabaseHas(
            'agents',
            [
                'id' =>
                    $agent->id,

                'home_village_id' =>
                    $second[
                        'village_id'
                    ],

                'updated_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_user_id_cannot_be_changed_through_update(): void
    {
        $admin =
            $this->admin();

        $firstUser =
            $this->agentUser(
                'Agent One',
                'agent1@test.com',
                '0788000011'
            );

        $secondUser =
            $this->agentUser(
                'Agent Two',
                'agent2@test.com',
                '0788000012'
            );

        $agent =
            $this->agent(
                $admin,
                $firstUser
            );

        Sanctum::actingAs(
            $admin
        );

        $this->putJson(
            "/api/admin/agents/{$agent->id}",
            [
                'user_id' =>
                    $secondUser->id,

                'notes' =>
                    'Attempted user change.',
            ]
        )->assertOk();

        $this->assertDatabaseHas(
            'agents',
            [
                'id' =>
                    $agent->id,

                'user_id' =>
                    $firstUser->id,
            ]
        );
    }

    public function test_status_cannot_be_changed_through_normal_update(): void
    {
        $admin =
            $this->admin();

        $agent =
            $this->agent(
                $admin,
                $this->agentUser()
            );

        Sanctum::actingAs(
            $admin
        );

        $this->putJson(
            "/api/admin/agents/{$agent->id}",
            [
                'status' =>
                    Agent::STATUS_INACTIVE,

                'notes' =>
                    'Normal update.',
            ]
        )->assertOk();

        $this->assertDatabaseHas(
            'agents',
            [
                'id' =>
                    $agent->id,

                'status' =>
                    Agent::STATUS_ACTIVE,
            ]
        );
    }

    public function test_admin_can_deactivate_agent(): void
    {
        $admin =
            $this->admin();

        $agent =
            $this->agent(
                $admin,
                $this->agentUser()
            );

        Sanctum::actingAs(
            $admin
        );

        $this->patchJson(
            "/api/admin/agents/{$agent->id}/deactivate"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                Agent::STATUS_INACTIVE
            )
            ->assertJsonPath(
                'data.is_active',
                false
            );

        $this->assertDatabaseHas(
            'agents',
            [
                'id' =>
                    $agent->id,

                'deactivated_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_admin_can_reactivate_agent(): void
    {
        $admin =
            $this->admin();

        $agent =
            $this->agent(
                $admin,
                $this->agentUser(),
                null,
                Agent::STATUS_INACTIVE
            );

        Sanctum::actingAs(
            $admin
        );

        $this->patchJson(
            "/api/admin/agents/{$agent->id}/reactivate"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                Agent::STATUS_ACTIVE
            );

        $this->assertDatabaseHas(
            'agents',
            [
                'id' =>
                    $agent->id,

                'status' =>
                    Agent::STATUS_ACTIVE,

                'reactivated_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_operational_lookup_returns_active_agents(): void
    {
        $admin =
            $this->admin();

        $agentUser =
            $this->agentUser();

        $agent =
            $this->agent(
                $admin,
                $agentUser
            );

        $accountant =
            User::factory()
                ->accountant()
                ->create([
                    'must_change_password' =>
                        false,
                ]);

        Sanctum::actingAs(
            $accountant
        );

        $this->getJson(
            '/api/agents/lookup?search=Field'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.id',
                $agent->id
            );
    }

    public function test_inactive_agent_is_excluded_from_operational_lookup(): void
    {
        $admin =
            $this->admin();

        $this->agent(
            $admin,
            $this->agentUser(),
            null,
            Agent::STATUS_INACTIVE
        );

        Sanctum::actingAs(
            User::factory()
                ->accountant()
                ->create([
                    'must_change_password' =>
                        false,
                ])
        );

        $this->getJson(
            '/api/agents/lookup'
        )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.items'
            );
    }

    public function test_non_admin_cannot_manage_agents(): void
    {
        $accountant =
            User::factory()
                ->accountant()
                ->create([
                    'must_change_password' =>
                        false,
                ]);

        Sanctum::actingAs(
            $accountant
        );

        $this->postJson(
            '/api/admin/agents',
            [
                'user_id' =>
                    $this
                        ->agentUser()
                        ->id,
            ]
        )->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_lookup_agents(): void
    {
        $this->getJson(
            '/api/agents/lookup'
        )->assertUnauthorized();
    }

    public function test_admin_can_search_filter_and_paginate_agents(): void
    {
        $admin =
            $this->admin();

        $location =
            $this->location('A');

        $this->agent(
            $admin,
            $this->agentUser(
                'Field Agent One',
                'agent1@test.com',
                '0788000011'
            ),
            $location,
            Agent::STATUS_ACTIVE,
            'AGT-000001'
        );

        $this->agent(
            $admin,
            $this->agentUser(
                'Other Agent',
                'agent2@test.com',
                '0788000012'
            ),
            $location,
            Agent::STATUS_INACTIVE,
            'AGT-000002'
        );

        Sanctum::actingAs(
            $admin
        );

        $url =
            '/api/admin/agents'
            . '?search=Field'
            . '&status=active'
            . '&village_id='
            . $location['village_id']
            . '&per_page=10';

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.items.0.agent_code',
                'AGT-000001'
            );
    }

    private function admin(): User
    {
        return User::factory()
            ->admin()
            ->create([
                'must_change_password' =>
                    false,
            ]);
    }

    private function agentUser(
        string $name =
            'Field Agent',
        string $email =
            'agent@test.com',
        string $phone =
            '0788000010'
    ): User {
        return User::factory()
            ->create([
                'name' =>
                    $name,

                'email' =>
                    $email,

                'phone' =>
                    $phone,

                'role' =>
                    User::ROLE_AGENT,

                'status' =>
                    'active',

                'must_change_password' =>
                    false,
            ]);
    }

    private function agent(
        User $admin,
        User $agentUser,
        ?array $location = null,
        string $status =
            Agent::STATUS_ACTIVE,
        string $code =
            'AGT-000001'
    ): Agent {
        return Agent::create([
            'agent_code' =>
                $code,

            'user_id' =>
                $agentUser->id,

            'home_village_id' =>
                $location[
                    'village_id'
                ] ?? null,

            'status' =>
                $status,

            'created_by' =>
                $admin->id,

            'deactivated_by' =>
                $status ===
                Agent::STATUS_INACTIVE
                    ? $admin->id
                    : null,

            'deactivated_at' =>
                $status ===
                Agent::STATUS_INACTIVE
                    ? now()
                    : null,
        ]);
    }

    private function location(
        string $suffix
    ): array {
        $now = now();

        $provinceId =
            DB::table('provinces')
                ->insertGetId([
                    'name' =>
                        "Province {$suffix}",

                    'is_active' =>
                        true,

                    'created_at' =>
                        $now,

                    'updated_at' =>
                        $now,
                ]);

        $districtId =
            DB::table('districts')
                ->insertGetId([
                    'province_id' =>
                        $provinceId,

                    'name' =>
                        "District {$suffix}",

                    'is_active' =>
                        true,

                    'created_at' =>
                        $now,

                    'updated_at' =>
                        $now,
                ]);

        $sectorId =
            DB::table('sectors')
                ->insertGetId([
                    'district_id' =>
                        $districtId,

                    'name' =>
                        "Sector {$suffix}",

                    'is_active' =>
                        true,

                    'created_at' =>
                        $now,

                    'updated_at' =>
                        $now,
                ]);

        $cellId =
            DB::table('cells')
                ->insertGetId([
                    'sector_id' =>
                        $sectorId,

                    'name' =>
                        "Cell {$suffix}",

                    'is_active' =>
                        true,

                    'created_at' =>
                        $now,

                    'updated_at' =>
                        $now,
                ]);

        $villageId =
            DB::table('villages')
                ->insertGetId([
                    'cell_id' =>
                        $cellId,

                    'name' =>
                        "Village {$suffix}",

                    'is_active' =>
                        true,

                    'created_at' =>
                        $now,

                    'updated_at' =>
                        $now,
                ]);

        return [
            'province_id' =>
                $provinceId,

            'district_id' =>
                $districtId,

            'sector_id' =>
                $sectorId,

            'cell_id' =>
                $cellId,

            'village_id' =>
                $villageId,
        ];
    }

    private function createRoles(): void
    {
        $roles = [
            ['admin', 'Admin'],
            [
                'accountant',
                'Accountant',
            ],
            [
                'balance',
                'Balance Officer',
            ],
            ['agent', 'Agent'],
            ['driver', 'Driver'],
            [
                'store',
                'Store Officer',
            ],
        ];

        foreach (
            $roles as [
                $name,
                $displayName,
            ]
        ) {
            DB::table('roles')
                ->insert([
                    'name' =>
                        $name,

                    'display_name' =>
                        $displayName,

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
