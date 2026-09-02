<?php

namespace Tests\Feature\API\Farmer;

use App\Models\Farmer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FarmerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_admin_can_create_farmer_with_generated_code(): void
    {
        $admin = $this->admin();

        $location =
            $this->location(
                'A'
            );

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/admin/farmers',
            [
                'full_name' =>
                    'Jean Bosco',

                'phone' =>
                    '0788000001',

                'national_id' =>
                    '1199880012345678',

                'gender' =>
                    Farmer::GENDER_MALE,

                'village_id' =>
                    $location[
                        'village_id'
                    ],

                'collection_point_id' =>
                    $location[
                        'collection_point_id'
                    ],

                'preferred_payment_method' =>
                    Farmer::PAYMENT_MOBILE_MONEY,
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.farmer_code',
                'FRM-000001'
            )
            ->assertJsonPath(
                'data.full_name',
                'Jean Bosco'
            )
            ->assertJsonPath(
                'data.status',
                Farmer::STATUS_ACTIVE
            );

        $this->assertDatabaseHas(
            'farmers',
            [
                'farmer_code' =>
                    'FRM-000001',

                'phone' =>
                    '0788000001',

                'status' =>
                    Farmer::STATUS_ACTIVE,

                'created_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_backend_generates_sequential_farmer_codes(): void
    {
        $admin =
            $this->admin();

        $location =
            $this->location('A');

        Sanctum::actingAs($admin);

        $this->createFarmerThroughApi(
            $location,
            'Jean One',
            '0788000001'
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.farmer_code',
                'FRM-000001'
            );

        $this->createFarmerThroughApi(
            $location,
            'Jean Two',
            '0788000002'
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.farmer_code',
                'FRM-000002'
            );
    }

    public function test_duplicate_farmer_phone_is_rejected(): void
    {
        $admin =
            $this->admin();

        $location =
            $this->location('A');

        Sanctum::actingAs($admin);

        $this->createFarmerThroughApi(
            $location,
            'Farmer One',
            '0788000001'
        )->assertCreated();

        $this->createFarmerThroughApi(
            $location,
            'Farmer Two',
            '0788000001'
        )->assertUnprocessable();
    }

    public function test_duplicate_national_id_is_rejected(): void
    {
        $admin =
            $this->admin();

        $location =
            $this->location('A');

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/admin/farmers',
            [
                'full_name' =>
                    'Farmer One',

                'phone' =>
                    '0788000001',

                'national_id' =>
                    '1199880012345678',

                'village_id' =>
                    $location[
                        'village_id'
                    ],
            ]
        )->assertCreated();

        $this->postJson(
            '/api/admin/farmers',
            [
                'full_name' =>
                    'Farmer Two',

                'phone' =>
                    '0788000002',

                'national_id' =>
                    '1199880012345678',

                'village_id' =>
                    $location[
                        'village_id'
                    ],
            ]
        )->assertUnprocessable();
    }

    public function test_collection_point_must_belong_to_selected_village(): void
    {
        $admin =
            $this->admin();

        $first =
            $this->location('A');

        $second =
            $this->location('B');

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/admin/farmers',
            [
                'full_name' =>
                    'Wrong Location Farmer',

                'phone' =>
                    '0788000001',

                'village_id' =>
                    $first[
                        'village_id'
                    ],

                'collection_point_id' =>
                    $second[
                        'collection_point_id'
                    ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'collection_point_id'
            );
    }

    public function test_admin_can_view_farmer(): void
    {
        $admin =
            $this->admin();

        $location =
            $this->location('A');

        $farmer =
            $this->farmer(
                $admin,
                $location
            );

        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/admin/farmers/{$farmer->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $farmer->id
            )
            ->assertJsonPath(
                'data.location.village.id',
                $location[
                    'village_id'
                ]
            );
    }

    public function test_admin_can_update_farmer(): void
    {
        $admin =
            $this->admin();

        $location =
            $this->location('A');

        $farmer =
            $this->farmer(
                $admin,
                $location
            );

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/farmers/{$farmer->id}",
            [
                'full_name' =>
                    'Updated Farmer',

                'phone' =>
                    '0788999999',

                'national_id' =>
                    null,

                'gender' =>
                    Farmer::GENDER_MALE,

                'village_id' =>
                    $location[
                        'village_id'
                    ],

                'collection_point_id' =>
                    $location[
                        'collection_point_id'
                    ],

                'preferred_payment_method' =>
                    Farmer::PAYMENT_CASH,

                'notes' =>
                    'Updated farmer profile.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.full_name',
                'Updated Farmer'
            )
            ->assertJsonPath(
                'data.farmer_code',
                $farmer->farmer_code
            );

        $this->assertDatabaseHas(
            'farmers',
            [
                'id' =>
                    $farmer->id,

                'full_name' =>
                    'Updated Farmer',

                'updated_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_status_cannot_be_changed_through_normal_update(): void
    {
        $admin =
            $this->admin();

        $location =
            $this->location('A');

        $farmer =
            $this->farmer(
                $admin,
                $location
            );

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/farmers/{$farmer->id}",
            [
                'full_name' =>
                    $farmer->full_name,

                'phone' =>
                    $farmer->phone,

                'village_id' =>
                    $location[
                        'village_id'
                    ],

                'status' =>
                    Farmer::STATUS_INACTIVE,
            ]
        )->assertOk();

        $this->assertDatabaseHas(
            'farmers',
            [
                'id' =>
                    $farmer->id,

                'status' =>
                    Farmer::STATUS_ACTIVE,
            ]
        );
    }

    public function test_admin_can_deactivate_farmer(): void
    {
        $admin =
            $this->admin();

        $location =
            $this->location('A');

        $farmer =
            $this->farmer(
                $admin,
                $location
            );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/farmers/{$farmer->id}/deactivate"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                Farmer::STATUS_INACTIVE
            )
            ->assertJsonPath(
                'data.is_active',
                false
            );

        $this->assertDatabaseHas(
            'farmers',
            [
                'id' =>
                    $farmer->id,

                'deactivated_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_admin_can_reactivate_farmer(): void
    {
        $admin =
            $this->admin();

        $location =
            $this->location('A');

        $farmer =
            $this->farmer(
                $admin,
                $location,
                Farmer::STATUS_INACTIVE
            );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/farmers/{$farmer->id}/reactivate"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                Farmer::STATUS_ACTIVE
            );

        $this->assertDatabaseHas(
            'farmers',
            [
                'id' =>
                    $farmer->id,

                'status' =>
                    Farmer::STATUS_ACTIVE,

                'reactivated_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_authenticated_operational_user_can_lookup_active_farmers(): void
    {
        $admin =
            $this->admin();

        $location =
            $this->location('A');

        $farmer =
            $this->farmer(
                $admin,
                $location
            );

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
            '/api/farmers/lookup?search=Jean'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.id',
                $farmer->id
            );
    }

    public function test_inactive_farmer_does_not_appear_in_operational_lookup(): void
    {
        $admin =
            $this->admin();

        $location =
            $this->location('A');

        $this->farmer(
            $admin,
            $location,
            Farmer::STATUS_INACTIVE
        );

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
            '/api/farmers/lookup'
        )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.items'
            );
    }

    public function test_non_admin_cannot_manage_farmers(): void
    {
        $location =
            $this->location('A');

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
            '/api/admin/farmers',
            [
                'full_name' =>
                    'Farmer One',

                'phone' =>
                    '0788000001',

                'village_id' =>
                    $location[
                        'village_id'
                    ],
            ]
        )->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_lookup_farmers(): void
    {
        $this->getJson(
            '/api/farmers/lookup'
        )->assertUnauthorized();
    }

    public function test_admin_can_search_filter_and_paginate_farmers(): void
    {
        $admin =
            $this->admin();

        $location =
            $this->location('A');

        $this->farmer(
            $admin,
            $location,
            Farmer::STATUS_ACTIVE,
            'FRM-000001',
            'Jean Bosco',
            '0788000001'
        );

        $this->farmer(
            $admin,
            $location,
            Farmer::STATUS_INACTIVE,
            'FRM-000002',
            'Emmanuel Farmer',
            '0788000002'
        );

        Sanctum::actingAs($admin);

        $url =
            '/api/admin/farmers'
            . '?search=Jean'
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
                'data.items.0.farmer_code',
                'FRM-000001'
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

    private function farmer(
        User $user,
        array $location,
        string $status =
            Farmer::STATUS_ACTIVE,
        string $code =
            'FRM-000001',
        string $name =
            'Jean Bosco',
        string $phone =
            '0788000001'
    ): Farmer {
        return Farmer::create([
            'farmer_code' =>
                $code,

            'full_name' =>
                $name,

            'phone' =>
                $phone,

            'gender' =>
                Farmer::GENDER_MALE,

            'village_id' =>
                $location[
                    'village_id'
                ],

            'collection_point_id' =>
                $location[
                    'collection_point_id'
                ],

            'preferred_payment_method' =>
                Farmer::PAYMENT_CASH,

            'status' =>
                $status,

            'created_by' =>
                $user->id,

            'deactivated_by' =>
                $status ===
                Farmer::STATUS_INACTIVE
                    ? $user->id
                    : null,

            'deactivated_at' =>
                $status ===
                Farmer::STATUS_INACTIVE
                    ? now()
                    : null,
        ]);
    }

    private function createFarmerThroughApi(
        array $location,
        string $name,
        string $phone
    ) {
        return $this->postJson(
            '/api/admin/farmers',
            [
                'full_name' =>
                    $name,

                'phone' =>
                    $phone,

                'village_id' =>
                    $location[
                        'village_id'
                    ],

                'collection_point_id' =>
                    $location[
                        'collection_point_id'
                    ],
            ]
        );
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

        $collectionPointId =
            DB::table(
                'collection_points'
            )->insertGetId([
                'village_id' =>
                    $villageId,

                'name' =>
                    "Collection Point {$suffix}",

                'code' =>
                    "CP-{$suffix}",

                'description' =>
                    null,

                'latitude' =>
                    null,

                'longitude' =>
                    null,

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

            'collection_point_id' =>
                $collectionPointId,
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
