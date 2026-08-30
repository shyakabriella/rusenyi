<?php

namespace Tests\Feature\API\Location;

use App\Models\Cell;
use App\Models\CollectionPoint;
use App\Models\District;
use App\Models\Province;
use App\Models\Sector;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_admin_can_create_full_location_hierarchy(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create([
                    'must_change_password' =>
                        false,
                ]);

        Sanctum::actingAs($admin);

        $provinceResponse =
            $this->postJson(
                '/api/admin/locations/provinces',
                [
                    'name' =>
                        'Western Province',
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'success',
                    true
                );

        $provinceId =
            $provinceResponse->json(
                'data.id'
            );

        $districtResponse =
            $this->postJson(
                '/api/admin/locations/districts',
                [
                    'province_id' =>
                        $provinceId,

                    'name' =>
                        'Nyamasheke',
                ]
            )->assertCreated();

        $districtId =
            $districtResponse->json(
                'data.id'
            );

        $sectorResponse =
            $this->postJson(
                '/api/admin/locations/sectors',
                [
                    'district_id' =>
                        $districtId,

                    'name' =>
                        'Gihombo',
                ]
            )->assertCreated();

        $sectorId =
            $sectorResponse->json(
                'data.id'
            );

        $cellResponse =
            $this->postJson(
                '/api/admin/locations/cells',
                [
                    'sector_id' =>
                        $sectorId,

                    'name' =>
                        'Rusenyi',
                ]
            )->assertCreated();

        $cellId =
            $cellResponse->json(
                'data.id'
            );

        $villageResponse =
            $this->postJson(
                '/api/admin/locations/villages',
                [
                    'cell_id' =>
                        $cellId,

                    'name' =>
                        'Gihombo Village',
                ]
            )->assertCreated();

        $villageId =
            $villageResponse->json(
                'data.id'
            );

        $this->postJson(
            '/api/admin/locations/collection-points',
            [
                'village_id' =>
                    $villageId,

                'name' =>
                    'Gihombo Main Collection Point',

                'code' =>
                    'CP-GIH-001',

                'latitude' =>
                    -2.1234567,

                'longitude' =>
                    29.1234567,
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                'CP-GIH-001'
            );

        $this->assertDatabaseHas(
            'collection_points',
            [
                'code' =>
                    'CP-GIH-001',
            ]
        );
    }

    public function test_admin_location_list_supports_search_status_and_pagination(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Province::create([
            'name' =>
                'Western Province',
            'is_active' =>
                true,
        ]);

        Province::create([
            'name' =>
                'Eastern Province',
            'is_active' =>
                false,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/admin/locations/provinces?search=Western&status=active&per_page=10'
        )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.items.0.name',
                'Western Province'
            )
            ->assertJsonPath(
                'data.pagination.total',
                1
            );
    }

    public function test_authenticated_user_can_use_dependent_location_dropdowns(): void
    {
        [
            $province,
            $district,
            $sector,
            $cell,
            $village,
        ] = $this->createHierarchy();

        $agent =
            User::factory()
                ->create([
                    'role' =>
                        User::ROLE_AGENT,
                    'must_change_password' =>
                        false,
                ]);

        Sanctum::actingAs($agent);

        $this->getJson(
            "/api/locations/districts?province_id={$province->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $district->id
            );

        $this->getJson(
            "/api/locations/sectors?district_id={$district->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $sector->id
            );

        $this->getJson(
            "/api/locations/cells?sector_id={$sector->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $cell->id
            );

        $this->getJson(
            "/api/locations/villages?cell_id={$cell->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $village->id
            );
    }

    public function test_unauthenticated_user_cannot_use_location_lookup(): void
    {
        $this->getJson(
            '/api/locations/provinces'
        )->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_locations(): void
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
            '/api/admin/locations/provinces',
            [
                'name' =>
                    'Test Province',
            ]
        )->assertForbidden();
    }

    public function test_admin_can_update_and_deactivate_location(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $province =
            Province::create([
                'name' =>
                    'Western Province',
            ]);

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/locations/provinces/{$province->id}",
            [
                'name' =>
                    'Western',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Western'
            );

        $this->patchJson(
            "/api/admin/locations/provinces/{$province->id}/status",
            [
                'is_active' =>
                    false,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.is_active',
                false
            );
    }

    public function test_duplicate_location_in_same_parent_is_rejected(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $province =
            Province::create([
                'name' =>
                    'Western Province',
            ]);

        District::create([
            'province_id' =>
                $province->id,

            'name' =>
                'Nyamasheke',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/admin/locations/districts',
            [
                'province_id' =>
                    $province->id,

                'name' =>
                    'Nyamasheke',
            ]
        )->assertUnprocessable();
    }

    public function test_collection_points_can_be_filtered_by_village(): void
    {
        [
            ,
            ,
            ,
            ,
            $village,
        ] = $this->createHierarchy();

        $otherVillage =
            Village::create([
                'cell_id' =>
                    $village->cell_id,

                'name' =>
                    'Other Village',
            ]);

        CollectionPoint::create([
            'village_id' =>
                $village->id,

            'name' =>
                'Point One',

            'code' =>
                'CP-001',
        ]);

        CollectionPoint::create([
            'village_id' =>
                $otherVillage->id,

            'name' =>
                'Point Two',

            'code' =>
                'CP-002',
        ]);

        $admin =
            User::factory()
                ->admin()
                ->create();

        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/admin/locations/collection-points?village_id={$village->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.items.0.code',
                'CP-001'
            );
    }

    public function test_admin_can_assign_agent_to_collection_points(): void
    {
        [
            ,
            ,
            ,
            ,
            $village,
        ] = $this->createHierarchy();

        $pointOne =
            CollectionPoint::create([
                'village_id' =>
                    $village->id,

                'name' =>
                    'Point One',

                'code' =>
                    'CP-001',
            ]);

        $pointTwo =
            CollectionPoint::create([
                'village_id' =>
                    $village->id,

                'name' =>
                    'Point Two',

                'code' =>
                    'CP-002',
            ]);

        $admin =
            User::factory()
                ->admin()
                ->create();

        $agent =
            User::factory()
                ->create([
                    'role' =>
                        User::ROLE_AGENT,
                ]);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/locations/agents/{$agent->id}/collection-points",
            [
                'collection_point_ids' => [
                    $pointOne->id,
                    $pointTwo->id,
                ],
            ]
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data'
            );

        $this->assertDatabaseHas(
            'agent_collection_points',
            [
                'user_id' =>
                    $agent->id,

                'collection_point_id' =>
                    $pointOne->id,

                'is_active' =>
                    true,
            ]
        );
    }

    public function test_admin_can_deactivate_agent_collection_point_assignment(): void
    {
        [
            ,
            ,
            ,
            ,
            $village,
        ] = $this->createHierarchy();

        $point =
            CollectionPoint::create([
                'village_id' =>
                    $village->id,

                'name' =>
                    'Point One',

                'code' =>
                    'CP-001',
            ]);

        $agent =
            User::factory()
                ->create([
                    'role' =>
                        User::ROLE_AGENT,
                ]);

        $agent
            ->collectionPoints()
            ->attach(
                $point->id,
                [
                    'is_active' =>
                        true,
                    'assigned_at' =>
                        now(),
                ]
            );

        $admin =
            User::factory()
                ->admin()
                ->create();

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/locations/agents/{$agent->id}/collection-points/{$point->id}/status",
            [
                'is_active' =>
                    false,
            ]
        )->assertOk();

        $this->assertDatabaseHas(
            'agent_collection_points',
            [
                'user_id' =>
                    $agent->id,

                'collection_point_id' =>
                    $point->id,

                'is_active' =>
                    false,
            ]
        );
    }

    public function test_non_agent_cannot_receive_collection_point_assignment(): void
    {
        [
            ,
            ,
            ,
            ,
            $village,
        ] = $this->createHierarchy();

        $point =
            CollectionPoint::create([
                'village_id' =>
                    $village->id,

                'name' =>
                    'Point One',

                'code' =>
                    'CP-001',
            ]);

        $driver =
            User::factory()
                ->create([
                    'role' =>
                        User::ROLE_DRIVER,
                ]);

        $admin =
            User::factory()
                ->admin()
                ->create();

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/locations/agents/{$driver->id}/collection-points",
            [
                'collection_point_ids' => [
                    $point->id,
                ],
            ]
        )->assertUnprocessable();
    }

    private function createHierarchy(): array
    {
        $province =
            Province::create([
                'name' =>
                    'Western Province',
            ]);

        $district =
            District::create([
                'province_id' =>
                    $province->id,

                'name' =>
                    'Nyamasheke',
            ]);

        $sector =
            Sector::create([
                'district_id' =>
                    $district->id,

                'name' =>
                    'Gihombo',
            ]);

        $cell =
            Cell::create([
                'sector_id' =>
                    $sector->id,

                'name' =>
                    'Rusenyi',
            ]);

        $village =
            Village::create([
                'cell_id' =>
                    $cell->id,

                'name' =>
                    'Test Village',
            ]);

        return [
            $province,
            $district,
            $sector,
            $cell,
            $village,
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
