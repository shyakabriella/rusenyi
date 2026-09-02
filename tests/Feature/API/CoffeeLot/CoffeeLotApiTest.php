<?php

namespace Tests\Feature\API\CoffeeLot;

use App\Models\Agent;
use App\Models\AgentCollection;
use App\Models\CoffeeLot;
use App\Models\CoffeePrice;
use App\Models\CoffeePurchase;
use App\Models\CoffeeSeason;
use App\Models\DirectFarmerDelivery;
use App\Models\FactoryReception;
use App\Models\Farmer;
use App\Models\FieldWeighing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoffeeLotApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_admin_can_create_lot_from_confirmed_factory_reception(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $source = $this->factorySource(
            $admin,
            $balance,
            500,
            493
        );

        Sanctum::actingAs($admin);

        $this->postJson('/api/coffee-lots', [
            'source_type' =>
                CoffeeLot::SOURCE_FACTORY_RECEPTION,

            'source_id' =>
                $source->id,

            'bag_count' =>
                8,

            'storage_location' =>
                'Warehouse A',

            'lot_date' =>
                now()->toDateString(),

            'notes' =>
                'Received from Agent collection',
        ])
            ->assertCreated()
            ->assertJsonPath(
                'data.lot_code',
                'LOT-000001'
            )
            ->assertJsonPath(
                'data.source_type',
                CoffeeLot::SOURCE_FACTORY_RECEPTION
            )
            ->assertJsonPath(
                'data.source_id',
                $source->id
            )
            ->assertJsonPath(
                'data.initial_weight_kg',
                '493.00'
            )
            ->assertJsonPath(
                'data.current_weight_kg',
                '493.00'
            )
            ->assertJsonPath(
                'data.processing_stage',
                CoffeeLot::STAGE_RECEIVED
            )
            ->assertJsonPath(
                'data.status',
                CoffeeLot::STATUS_ACTIVE
            );
    }

    public function test_backend_generates_sequential_lot_codes(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $season = $this->season($admin);

        $first = $this->factorySource(
            $admin,
            $balance,
            300,
            298,
            $season
        );

        $second = $this->factorySource(
            $admin,
            $balance,
            400,
            397,
            $season
        );

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/coffee-lots',
            $this->lotPayload(
                $first->id
            )
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.lot_code',
                'LOT-000001'
            );

        $this->postJson(
            '/api/coffee-lots',
            $this->lotPayload(
                $second->id
            )
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.lot_code',
                'LOT-000002'
            );
    }

    public function test_weight_and_coffee_type_are_derived_from_source(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $source = $this->factorySource(
            $admin,
            $balance,
            600,
            595
        );

        Sanctum::actingAs($admin);

        $this->postJson('/api/coffee-lots', [
            'source_type' =>
                CoffeeLot::SOURCE_FACTORY_RECEPTION,

            'source_id' =>
                $source->id,

            'initial_weight_kg' =>
                999999,

            'current_weight_kg' =>
                999999,

            'coffee_type' =>
                'fake-coffee',

            'lot_date' =>
                now()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath(
                'data.initial_weight_kg',
                '595.00'
            )
            ->assertJsonPath(
                'data.current_weight_kg',
                '595.00'
            )
            ->assertJsonPath(
                'data.coffee_type',
                CoffeePrice::TYPE_PARCHMENT
            );
    }

    public function test_draft_factory_reception_cannot_create_lot(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $source = $this->factorySource(
            $admin,
            $balance,
            400,
            397
        );

        $source->update([
            'status' =>
                FactoryReception::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/coffee-lots',
            $this->lotPayload(
                $source->id
            )
        )->assertUnprocessable();
    }

    public function test_source_cannot_create_two_lots(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $source = $this->factorySource(
            $admin,
            $balance,
            500,
            495
        );

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/coffee-lots',
            $this->lotPayload(
                $source->id
            )
        )->assertCreated();

        $this->postJson(
            '/api/coffee-lots',
            $this->lotPayload(
                $source->id
            )
        )->assertUnprocessable();
    }

    public function test_store_officer_can_create_coffee_lot(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');
        $store = $this->user('store');

        $source = $this->factorySource(
            $admin,
            $balance,
            250,
            248
        );

        Sanctum::actingAs($store);

        $this->postJson(
            '/api/coffee-lots',
            $this->lotPayload(
                $source->id
            )
        )->assertCreated();
    }

    public function test_accountant_can_view_but_cannot_create_lot(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $source = $this->factorySource(
            $admin,
            $balance,
            200,
            198
        );

        $this->createLot(
            $admin,
            $source
        );

        $accountant =
            $this->user('accountant');

        Sanctum::actingAs($accountant);

        $this->getJson(
            '/api/coffee-lots'
        )->assertOk();

        $this->postJson(
            '/api/coffee-lots',
            $this->lotPayload(
                $source->id
            )
        )->assertForbidden();
    }

    public function test_unrelated_roles_cannot_access_coffee_lots(): void
    {
        foreach ([
            'agent',
            'balance',
            'driver',
        ] as $role) {
            Sanctum::actingAs(
                $this->user($role)
            );

            $this->getJson(
                '/api/coffee-lots'
            )->assertForbidden();
        }
    }

    public function test_confirmed_paid_direct_farmer_delivery_can_create_lot(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $delivery =
            $this->directDelivery(
                $admin,
                $balance,
                true
            );

        Sanctum::actingAs($admin);

        $this->postJson('/api/coffee-lots', [
            'source_type' =>
                CoffeeLot::SOURCE_DIRECT_FARMER_DELIVERY,

            'source_id' =>
                $delivery->id,

            'lot_date' =>
                now()->toDateString(),

            'storage_location' =>
                'Direct Delivery Store',
        ])
            ->assertCreated()
            ->assertJsonPath(
                'data.source_type',
                CoffeeLot::SOURCE_DIRECT_FARMER_DELIVERY
            )
            ->assertJsonPath(
                'data.initial_weight_kg',
                '120.00'
            )
            ->assertJsonPath(
                'data.current_weight_kg',
                '120.00'
            );
    }

    public function test_unpaid_direct_farmer_delivery_cannot_create_lot(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $delivery =
            $this->directDelivery(
                $admin,
                $balance,
                false
            );

        Sanctum::actingAs($admin);

        $this->postJson('/api/coffee-lots', [
            'source_type' =>
                CoffeeLot::SOURCE_DIRECT_FARMER_DELIVERY,

            'source_id' =>
                $delivery->id,

            'lot_date' =>
                now()->toDateString(),
        ])->assertUnprocessable();
    }

    public function test_eligible_sources_exclude_sources_already_used(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $season = $this->season($admin);

        $used = $this->factorySource(
            $admin,
            $balance,
            300,
            298,
            $season
        );

        $available = $this->factorySource(
            $admin,
            $balance,
            400,
            397,
            $season
        );

        $this->createLot(
            $admin,
            $used
        );

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/coffee-lots/eligible-sources'
        )->assertOk();

        $ids = collect(
            $response->json('data.items')
        )
            ->where(
                'source_type',
                CoffeeLot::SOURCE_FACTORY_RECEPTION
            )
            ->pluck('source_id');

        $this->assertFalse(
            $ids->contains($used->id)
        );

        $this->assertTrue(
            $ids->contains($available->id)
        );
    }

    public function test_admin_can_update_active_lot_metadata(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $source = $this->factorySource(
            $admin,
            $balance,
            500,
            495
        );

        $lot = $this->createLot(
            $admin,
            $source
        );

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/coffee-lots/{$lot->id}",
            [
                'bag_count' => 10,

                'storage_location' =>
                    'Warehouse B',

                'notes' =>
                    'Moved to Warehouse B',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.bag_count',
                10
            )
            ->assertJsonPath(
                'data.storage_location',
                'Warehouse B'
            )
            ->assertJsonPath(
                'data.current_weight_kg',
                '495.00'
            );
    }

    public function test_admin_can_close_active_lot(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $source = $this->factorySource(
            $admin,
            $balance,
            500,
            495
        );

        $lot = $this->createLot(
            $admin,
            $source
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/coffee-lots/{$lot->id}/close"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                CoffeeLot::STATUS_CLOSED
            )
            ->assertJsonPath(
                'data.closer.id',
                $admin->id
            );
    }

    public function test_closed_lot_cannot_be_updated_or_closed_again(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $source = $this->factorySource(
            $admin,
            $balance,
            500,
            495
        );

        $lot = $this->createLot(
            $admin,
            $source
        );

        $lot->update([
            'status' =>
                CoffeeLot::STATUS_CLOSED,

            'closed_by' =>
                $admin->id,

            'closed_at' =>
                now(),
        ]);

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/coffee-lots/{$lot->id}",
            [
                'storage_location' =>
                    'Changed',
            ]
        )->assertUnprocessable();

        $this->patchJson(
            "/api/coffee-lots/{$lot->id}/close"
        )->assertUnprocessable();
    }

    public function test_summary_returns_lot_counts_and_weights(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $season = $this->season($admin);

        $firstSource =
            $this->factorySource(
                $admin,
                $balance,
                500,
                495,
                $season
            );

        $secondSource =
            $this->factorySource(
                $admin,
                $balance,
                300,
                297,
                $season
            );

        $first = $this->createLot(
            $admin,
            $firstSource
        );

        $second = $this->createLot(
            $admin,
            $secondSource
        );

        $second->update([
            'status' =>
                CoffeeLot::STATUS_CLOSED,

            'closed_by' =>
                $admin->id,

            'closed_at' =>
                now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/coffee-lots/summary?coffee_season_id={$season->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.total_lots',
                2
            )
            ->assertJsonPath(
                'data.active_lots',
                1
            )
            ->assertJsonPath(
                'data.closed_lots',
                1
            )
            ->assertJsonPath(
                'data.initial_weight_kg',
                '792.00'
            )
            ->assertJsonPath(
                'data.current_weight_kg',
                '495.00'
            );
    }

    public function test_list_supports_search_status_and_source_filters(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $source = $this->factorySource(
            $admin,
            $balance,
            200,
            198
        );

        $lot = $this->createLot(
            $admin,
            $source
        );

        $lot->update([
            'storage_location' =>
                'Main Warehouse',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/coffee-lots?search=Main'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            );

        $this->getJson(
            '/api/coffee-lots?status=active'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            );

        $this->getJson(
            '/api/coffee-lots?source_type=factory_reception'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            );
    }

    public function test_unauthenticated_user_cannot_access_coffee_lots(): void
    {
        $this->getJson(
            '/api/coffee-lots'
        )->assertUnauthorized();
    }

    private function lotPayload(
        int $sourceId
    ): array {
        return [
            'source_type' =>
                CoffeeLot::SOURCE_FACTORY_RECEPTION,

            'source_id' =>
                $sourceId,

            'lot_date' =>
                now()->toDateString(),
        ];
    }

    private function createLot(
        User $admin,
        FactoryReception $source
    ): CoffeeLot {
        return CoffeeLot::create([
            'coffee_season_id' =>
                $source->coffee_season_id,

            'source_type' =>
                CoffeeLot::SOURCE_FACTORY_RECEPTION,

            'source_id' =>
                $source->id,

            'coffee_type' =>
                CoffeePrice::TYPE_PARCHMENT,

            'initial_weight_kg' =>
                $source->factory_weight_kg,

            'current_weight_kg' =>
                $source->factory_weight_kg,

            'processing_stage' =>
                CoffeeLot::STAGE_RECEIVED,

            'status' =>
                CoffeeLot::STATUS_ACTIVE,

            'lot_date' =>
                now()->toDateString(),

            'created_by' =>
                $admin->id,
        ]);
    }

    private function factorySource(
        User $admin,
        User $balance,
        float $quantity,
        float $factoryWeight,
        ?CoffeeSeason $season = null
    ): FactoryReception {
        $season ??=
            $this->season($admin);

        $point = $this->location();

        [$agent] =
            $this->agent($admin);

        $farmer =
            $this->farmer(
                $admin,
                $point
            );

        $price =
            $this->price(
                $season,
                $admin
            );

        $collection =
            AgentCollection::create([
                'coffee_season_id' =>
                    $season->id,

                'agent_id' =>
                    $agent->id,

                'collection_point_id' =>
                    $point->id,

                'collection_date' =>
                    now()
                        ->subDay()
                        ->toDateString(),

                'status' =>
                    AgentCollection::STATUS_COMPLETED,

                'created_by' =>
                    $admin->id,

                'completed_by' =>
                    $admin->id,

                'completed_at' =>
                    now()->subHours(3),
            ]);

        CoffeePurchase::create([
            'coffee_season_id' =>
                $season->id,

            'coffee_price_id' =>
                $price->id,

            'agent_id' =>
                $agent->id,

            'farmer_id' =>
                $farmer->id,

            'collection_point_id' =>
                $point->id,

            'agent_collection_id' =>
                $collection->id,

            'coffee_type' =>
                CoffeePrice::TYPE_PARCHMENT,

            'quantity_kg' =>
                $quantity,

            'price_per_kg' =>
                1000,

            'total_amount' =>
                $quantity * 1000,

            'currency' =>
                'RWF',

            'purchase_date' =>
                now()
                    ->subDay()
                    ->toDateString(),

            'status' =>
                CoffeePurchase::STATUS_APPROVED,

            'approved_by' =>
                $admin->id,

            'approved_at' =>
                now()->subHours(4),

            'created_by' =>
                $admin->id,
        ]);

        $fieldWeight =
            $quantity - 2;

        $fieldDifference =
            $fieldWeight - $quantity;

        $weighing =
            FieldWeighing::create([
                'coffee_season_id' =>
                    $season->id,

                'agent_collection_id' =>
                    $collection->id,

                'agent_id' =>
                    $agent->id,

                'collection_point_id' =>
                    $point->id,

                'balance_officer_id' =>
                    $balance->id,

                'expected_quantity_kg' =>
                    $quantity,

                'field_weight_kg' =>
                    $fieldWeight,

                'difference_kg' =>
                    $fieldDifference,

                'difference_percentage' =>
                    ($fieldDifference / $quantity)
                    * 100,

                'weighed_at' =>
                    now()->subHours(2),

                'status' =>
                    FieldWeighing::STATUS_CONFIRMED,

                'created_by' =>
                    $balance->id,

                'confirmed_by' =>
                    $balance->id,

                'confirmed_at' =>
                    now()->subHours(2),
            ]);

        $difference =
            $factoryWeight - $fieldWeight;

        return FactoryReception::create([
            'coffee_season_id' =>
                $season->id,

            'agent_collection_id' =>
                $collection->id,

            'field_weighing_id' =>
                $weighing->id,

            'agent_id' =>
                $agent->id,

            'collection_point_id' =>
                $point->id,

            'balance_officer_id' =>
                $balance->id,

            'field_weight_kg' =>
                $fieldWeight,

            'factory_weight_kg' =>
                $factoryWeight,

            'difference_kg' =>
                $difference,

            'difference_percentage' =>
                ($difference / $fieldWeight)
                * 100,

            'received_at' =>
                now()->subHour(),

            'status' =>
                FactoryReception::STATUS_CONFIRMED,

            'created_by' =>
                $balance->id,

            'confirmed_by' =>
                $balance->id,

            'confirmed_at' =>
                now()->subHour(),
        ]);
    }

    private function directDelivery(
        User $admin,
        User $balance,
        bool $paid
    ): DirectFarmerDelivery {
        $season =
            $this->season($admin);

        $point =
            $this->location();

        $farmer =
            $this->farmer(
                $admin,
                $point
            );

        $price =
            $this->price(
                $season,
                $admin
            );

        return DirectFarmerDelivery::create([
            'coffee_season_id' =>
                $season->id,

            'coffee_price_id' =>
                $price->id,

            'farmer_id' =>
                $farmer->id,

            'balance_officer_id' =>
                $balance->id,

            'collection_point_id' =>
                $point->id,

            'coffee_type' =>
                CoffeePrice::TYPE_PARCHMENT,

            'quantity_kg' =>
                120,

            'price_per_kg' =>
                1000,

            'total_amount' =>
                120000,

            'currency' =>
                'RWF',

            'delivery_date' =>
                now()->toDateString(),

            'status' =>
                'confirmed',

            'payment_status' =>
                $paid
                    ? 'paid'
                    : 'unpaid',

            'payment_method' =>
                $paid
                    ? 'cash'
                    : null,

            'payment_proof_path' =>
                $paid
                    ? 'test/proof.jpg'
                    : null,

            'created_by' =>
                $balance->id,

            'confirmed_by' =>
                $balance->id,

            'confirmed_at' =>
                now(),

            'paid_by' =>
                $paid
                    ? $balance->id
                    : null,

            'paid_at' =>
                $paid
                    ? now()
                    : null,
        ]);
    }

    private function agent(
        User $admin
    ): array {
        $user =
            $this->user('agent');

        $agent =
            Agent::create([
                'agent_code' =>
                    'AGT-' .
                    fake()
                        ->unique()
                        ->numerify('######'),

                'user_id' =>
                    $user->id,

                'status' =>
                    Agent::STATUS_ACTIVE,

                'created_by' =>
                    $admin->id,
            ]);

        return [
            $agent,
            $user,
        ];
    }

    private function farmer(
        User $admin,
        $point
    ): Farmer {
        return Farmer::create([
            'farmer_code' =>
                'FRM-' .
                    fake()
                        ->unique()
                        ->numerify('######'),

            'full_name' =>
                fake()->name(),

            'phone' =>
                fake()
                    ->unique()
                    ->numerify('078#######'),

            'village_id' =>
                $point->village_id,

            'collection_point_id' =>
                $point->id,

            'preferred_payment_method' =>
                Farmer::PAYMENT_CASH,

            'status' =>
                Farmer::STATUS_ACTIVE,

            'created_by' =>
                $admin->id,
        ]);
    }

    private function season(
        User $admin
    ): CoffeeSeason {
        $suffix =
            fake()
                ->unique()
                ->numerify('######');

        return CoffeeSeason::create([
            'name' =>
                "Lot Season {$suffix}",

            'code' =>
                "CS-{$suffix}",

            'start_date' =>
                now()
                    ->subMonth()
                    ->toDateString(),

            'end_date' =>
                now()
                    ->addMonths(3)
                    ->toDateString(),

            'status' =>
                CoffeeSeason::STATUS_ACTIVE,

            'created_by' =>
                $admin->id,

            'activated_by' =>
                $admin->id,

            'activated_at' =>
                now(),
        ]);
    }

    private function price(
        CoffeeSeason $season,
        User $admin
    ): CoffeePrice {
        $existing =
            CoffeePrice::query()
                ->where(
                    'coffee_season_id',
                    $season->id
                )
                ->where(
                    'coffee_type',
                    CoffeePrice::TYPE_PARCHMENT
                )
                ->first();

        if ($existing) {
            return $existing;
        }

        $suffix =
            fake()
                ->unique()
                ->numerify('######');

        return CoffeePrice::create([
            'coffee_season_id' =>
                $season->id,

            'code' =>
                "PRICE-{$suffix}",

            'coffee_type' =>
                CoffeePrice::TYPE_PARCHMENT,

            'price_per_kg' =>
                1000,

            'currency' =>
                'RWF',

            'effective_from' =>
                now()
                    ->subWeek()
                    ->toDateString(),

            'effective_to' =>
                now()
                    ->addMonth()
                    ->toDateString(),

            'status' =>
                CoffeePrice::STATUS_ACTIVE,

            'created_by' =>
                $admin->id,

            'activated_by' =>
                $admin->id,

            'activated_at' =>
                now(),
        ]);
    }

    private function location()
    {
        $suffix =
            fake()
                ->unique()
                ->numerify('######');

        $provinceId =
            DB::table('provinces')
                ->insertGetId([
                    'name' =>
                        "Province {$suffix}",
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $districtId =
            DB::table('districts')
                ->insertGetId([
                    'province_id' =>
                        $provinceId,
                    'name' =>
                        "District {$suffix}",
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $sectorId =
            DB::table('sectors')
                ->insertGetId([
                    'district_id' =>
                        $districtId,
                    'name' =>
                        "Sector {$suffix}",
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $cellId =
            DB::table('cells')
                ->insertGetId([
                    'sector_id' =>
                        $sectorId,
                    'name' =>
                        "Cell {$suffix}",
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $villageId =
            DB::table('villages')
                ->insertGetId([
                    'cell_id' =>
                        $cellId,
                    'name' =>
                        "Village {$suffix}",
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $pointId =
            DB::table('collection_points')
                ->insertGetId([
                    'village_id' =>
                        $villageId,
                    'name' =>
                        "Point {$suffix}",
                    'code' =>
                        "CP-{$suffix}",
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        return DB::table('collection_points')
            ->where(
                'id',
                $pointId
            )
            ->first();
    }

    private function user(
        string $role
    ): User {
        return User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function createRoles(): void
    {
        foreach ([
            ['admin', 'Admin'],
            ['accountant', 'Accountant'],
            ['balance', 'Balance Officer'],
            ['agent', 'Agent'],
            ['driver', 'Driver'],
            ['store', 'Store Officer'],
        ] as [$name, $displayName]) {
            DB::table('roles')
                ->updateOrInsert(
                    ['name' => $name],
                    [
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
                    ]
                );
        }
    }
}
