<?php

namespace Tests\Feature\API\AgentCollection;

use App\Models\Agent;
use App\Models\AgentCollection;
use App\Models\AgentWalletTransaction;
use App\Models\CoffeePrice;
use App\Models\CoffeePurchase;
use App\Models\CoffeeSeason;
use App\Models\Farmer;
use App\Models\User;
use App\Services\Finance\AgentWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentCollectionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_admin_can_create_open_collection_with_generated_code(): void
    {
        $admin = $this->user('admin');
        [$agent] = $this->agent();
        $season = $this->season($admin);

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/agent-collections',
            [
                'agent_id' => $agent->id,
                'collection_date' =>
                    now()->toDateString(),
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.collection_code',
                'COL-000001'
            )
            ->assertJsonPath(
                'data.agent_id',
                $agent->id
            )
            ->assertJsonPath(
                'data.coffee_season_id',
                $season->id
            )
            ->assertJsonPath(
                'data.status',
                AgentCollection::STATUS_OPEN
            );
    }

    public function test_agent_can_create_only_own_collection(): void
    {
        $admin = $this->user('admin');

        [$ownAgent, $agentUser] =
            $this->agent();

        [$otherAgent] =
            $this->agent();

        $this->season($admin);

        Sanctum::actingAs($agentUser);

        $this->postJson(
            '/api/agent-collections',
            [
                'agent_id' =>
                    $otherAgent->id,

                'collection_date' =>
                    now()->toDateString(),
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.agent_id',
                $ownAgent->id
            );
    }

    public function test_accountant_can_view_but_cannot_create_collection(): void
    {
        $admin = $this->user('admin');
        $accountant =
            $this->user('accountant');

        [$agent] = $this->agent();

        $season = $this->season($admin);

        $this->collection(
            $agent,
            $season,
            $admin
        );

        Sanctum::actingAs($accountant);

        $this->getJson(
            '/api/agent-collections'
        )->assertOk();

        $this->postJson(
            '/api/agent-collections',
            [
                'agent_id' => $agent->id,
                'collection_date' =>
                    now()->toDateString(),
            ]
        )->assertForbidden();
    }

    public function test_agent_sees_only_own_collections(): void
    {
        $admin = $this->user('admin');

        [$agentA, $userA] =
            $this->agent();

        [$agentB] =
            $this->agent();

        $season = $this->season($admin);

        $this->collection(
            $agentA,
            $season,
            $admin
        );

        $this->collection(
            $agentB,
            $season,
            $admin
        );

        Sanctum::actingAs($userA);

        $this->getJson(
            '/api/agent-collections'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.items.0.agent_id',
                $agentA->id
            );
    }

    public function test_eligible_purchases_returns_only_approved_unassigned_purchases(): void
    {
        $admin = $this->user('admin');
        [$agent] = $this->agent();

        $season = $this->season($admin);

        [$point] = $this->location();

        $approved = $this->purchase(
            $agent,
            $season,
            $admin,
            $point
        );

        $this->purchase(
            $agent,
            $season,
            $admin,
            $point,
            [
                'status' =>
                    CoffeePurchase::STATUS_DRAFT,
            ]
        );

        $collection = $this->collection(
            $agent,
            $season,
            $admin,
            $point
        );

        $assigned = $this->purchase(
            $agent,
            $season,
            $admin,
            $point
        );

        $assigned->update([
            'agent_collection_id' =>
                $collection->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/agent-collections/eligible-purchases'
            . '?agent_id=' . $agent->id
            . '&coffee_season_id=' . $season->id
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.items'
            )
            ->assertJsonPath(
                'data.items.0.id',
                $approved->id
            );
    }

    public function test_only_approved_purchase_can_be_added(): void
    {
        $admin = $this->user('admin');
        [$agent] = $this->agent();
        $season = $this->season($admin);

        [$point] = $this->location();

        $purchase = $this->purchase(
            $agent,
            $season,
            $admin,
            $point,
            [
                'status' =>
                    CoffeePurchase::STATUS_DRAFT,
            ]
        );

        $collection = $this->collection(
            $agent,
            $season,
            $admin,
            $point
        );

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/agent-collections/{$collection->id}/purchases",
            [
                'purchase_ids' => [
                    $purchase->id,
                ],
            ]
        )->assertUnprocessable();
    }

    public function test_purchase_from_another_agent_cannot_be_added(): void
    {
        $admin = $this->user('admin');

        [$agentA] = $this->agent();
        [$agentB] = $this->agent();

        $season = $this->season($admin);

        [$point] = $this->location();

        $purchase = $this->purchase(
            $agentB,
            $season,
            $admin,
            $point
        );

        $collection = $this->collection(
            $agentA,
            $season,
            $admin,
            $point
        );

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/agent-collections/{$collection->id}/purchases",
            [
                'purchase_ids' => [
                    $purchase->id,
                ],
            ]
        )->assertUnprocessable();
    }

    public function test_purchase_from_another_season_cannot_be_added(): void
    {
        $admin = $this->user('admin');
        [$agent] = $this->agent();

        $seasonA = $this->season($admin);

        $seasonB = $this->season(
            $admin,
            CoffeeSeason::STATUS_DRAFT
        );

        [$point] = $this->location();

        $purchase = $this->purchase(
            $agent,
            $seasonB,
            $admin,
            $point
        );

        $collection = $this->collection(
            $agent,
            $seasonA,
            $admin,
            $point
        );

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/agent-collections/{$collection->id}/purchases",
            [
                'purchase_ids' => [
                    $purchase->id,
                ],
            ]
        )->assertUnprocessable();
    }

    public function test_purchase_cannot_belong_to_two_collections(): void
    {
        $admin = $this->user('admin');
        [$agent] = $this->agent();
        $season = $this->season($admin);

        [$point] = $this->location();

        $purchase = $this->purchase(
            $agent,
            $season,
            $admin,
            $point
        );

        $first = $this->collection(
            $agent,
            $season,
            $admin,
            $point
        );

        $second = $this->collection(
            $agent,
            $season,
            $admin,
            $point
        );

        $purchase->update([
            'agent_collection_id' =>
                $first->id,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/agent-collections/{$second->id}/purchases",
            [
                'purchase_ids' => [
                    $purchase->id,
                ],
            ]
        )->assertUnprocessable();
    }

    public function test_collection_totals_are_calculated_from_purchases(): void
    {
        $admin = $this->user('admin');
        [$agent] = $this->agent();
        $season = $this->season($admin);

        [$point] = $this->location();

        $farmerA = $this->farmer(
            $admin,
            $point
        );

        $farmerB = $this->farmer(
            $admin,
            $point
        );

        $purchaseA = $this->purchase(
            $agent,
            $season,
            $admin,
            $point,
            [
                'farmer_id' =>
                    $farmerA->id,

                'quantity_kg' => 100,
                'total_amount' => 100000,
            ]
        );

        $purchaseB = $this->purchase(
            $agent,
            $season,
            $admin,
            $point,
            [
                'farmer_id' =>
                    $farmerB->id,

                'quantity_kg' => 50,
                'total_amount' => 50000,
            ]
        );

        $collection = $this->collection(
            $agent,
            $season,
            $admin,
            $point
        );

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/agent-collections/{$collection->id}/purchases",
            [
                'purchase_ids' => [
                    $purchaseA->id,
                    $purchaseB->id,
                ],
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.purchases_count',
                2
            )
            ->assertJsonPath(
                'data.farmers_count',
                2
            )
            ->assertJsonPath(
                'data.total_quantity_kg',
                '150.00'
            )
            ->assertJsonPath(
                'data.total_amount',
                '150000.00'
            );
    }

    public function test_collection_cannot_be_completed_without_purchase(): void
    {
        $admin = $this->user('admin');
        [$agent] = $this->agent();
        $season = $this->season($admin);

        $collection = $this->collection(
            $agent,
            $season,
            $admin
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/agent-collections/{$collection->id}/complete"
        )->assertUnprocessable();
    }

    public function test_completed_collection_is_locked(): void
    {
        $admin = $this->user('admin');
        [$agent] = $this->agent();
        $season = $this->season($admin);

        [$point] = $this->location();

        $purchase = $this->purchase(
            $agent,
            $season,
            $admin,
            $point
        );

        $collection = $this->collection(
            $agent,
            $season,
            $admin,
            $point
        );

        $purchase->update([
            'agent_collection_id' =>
                $collection->id,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/agent-collections/{$collection->id}/complete"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                AgentCollection::STATUS_COMPLETED
            );

        $this->putJson(
            "/api/agent-collections/{$collection->id}",
            [
                'collection_date' =>
                    now()->toDateString(),

                'collection_point_id' =>
                    $point->id,

                'notes' =>
                    'Trying to edit',
            ]
        )->assertUnprocessable();

        $this->deleteJson(
            "/api/agent-collections/{$collection->id}/purchases/{$purchase->id}"
        )->assertUnprocessable();
    }

    public function test_cancelling_open_collection_releases_purchases(): void
    {
        $admin = $this->user('admin');
        [$agent] = $this->agent();
        $season = $this->season($admin);

        [$point] = $this->location();

        $purchase = $this->purchase(
            $agent,
            $season,
            $admin,
            $point
        );

        $collection = $this->collection(
            $agent,
            $season,
            $admin,
            $point
        );

        $purchase->update([
            'agent_collection_id' =>
                $collection->id,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/agent-collections/{$collection->id}/cancel",
            [
                'cancellation_reason' =>
                    'Collection entered by mistake',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                AgentCollection::STATUS_CANCELLED
            );

        $this->assertDatabaseHas(
            'coffee_purchases',
            [
                'id' => $purchase->id,
                'agent_collection_id' => null,
            ]
        );
    }

    public function test_collection_does_not_debit_agent_wallet_again(): void
    {
        $admin = $this->user('admin');
        [$agent] = $this->agent();
        $season = $this->season($admin);

        [$point] = $this->location();

        $purchase = $this->purchase(
            $agent,
            $season,
            $admin,
            $point,
            [
                'total_amount' => 100000,
            ]
        );

        AgentWalletTransaction::create([
            'agent_id' => $agent->id,

            'coffee_season_id' =>
                $season->id,

            'type' =>
                AgentWalletTransaction::TYPE_CASH_ALLOCATION,

            'direction' =>
                AgentWalletTransaction::DIRECTION_CREDIT,

            'amount' => 500000,
            'currency' => 'RWF',
            'source_type' => 'test_allocation',
            'source_id' => 1,
            'created_by' => $admin->id,
        ]);

        $wallet = app(
            AgentWalletService::class
        );

        $wallet->recordPurchase(
            $purchase
        );

        $before = $wallet->balance(
            $agent->id,
            $season->id
        );

        $collection = $this->collection(
            $agent,
            $season,
            $admin,
            $point
        );

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/agent-collections/{$collection->id}/purchases",
            [
                'purchase_ids' => [
                    $purchase->id,
                ],
            ]
        )->assertOk();

        $this->patchJson(
            "/api/agent-collections/{$collection->id}/complete"
        )->assertOk();

        $after = $wallet->balance(
            $agent->id,
            $season->id
        );

        $this->assertSame(
            $before,
            $after
        );
    }

    public function test_purchase_in_completed_collection_cannot_be_cancelled(): void
    {
        $admin = $this->user('admin');
        [$agent] = $this->agent();
        $season = $this->season($admin);

        [$point] = $this->location();

        $purchase = $this->purchase(
            $agent,
            $season,
            $admin,
            $point
        );

        $collection = $this->collection(
            $agent,
            $season,
            $admin,
            $point
        );

        $purchase->update([
            'agent_collection_id' =>
                $collection->id,
        ]);

        $collection->update([
            'status' =>
                AgentCollection::STATUS_COMPLETED,

            'completed_by' =>
                $admin->id,

            'completed_at' =>
                now(),
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/coffee-purchases/{$purchase->id}/cancel",
            [
                'cancellation_reason' =>
                    'Trying to change completed collection',
            ]
        )->assertUnprocessable();

        $this->assertDatabaseHas(
            'coffee_purchases',
            [
                'id' => $purchase->id,
                'status' =>
                    CoffeePurchase::STATUS_APPROVED,
            ]
        );
    }

    public function test_unrelated_roles_cannot_access_agent_collections(): void
    {
        foreach ([
            'balance',
            'driver',
            'store',
        ] as $role) {
            Sanctum::actingAs(
                $this->user($role)
            );

            $this->getJson(
                '/api/agent-collections'
            )->assertForbidden();
        }
    }

    public function test_unauthenticated_user_cannot_access_agent_collections(): void
    {
        $this->getJson(
            '/api/agent-collections'
        )->assertUnauthorized();
    }

    private function collection(
        Agent $agent,
        CoffeeSeason $season,
        User $creator,
        $point = null
    ): AgentCollection {
        return AgentCollection::create([
            'coffee_season_id' =>
                $season->id,

            'agent_id' =>
                $agent->id,

            'collection_point_id' =>
                $point?->id,

            'collection_date' =>
                now()->toDateString(),

            'status' =>
                AgentCollection::STATUS_OPEN,

            'created_by' =>
                $creator->id,
        ]);
    }

    private function purchase(
        Agent $agent,
        CoffeeSeason $season,
        User $admin,
        $point,
        array $overrides = []
    ): CoffeePurchase {
        $farmerId =
            $overrides['farmer_id']
            ?? $this->farmer(
                $admin,
                $point
            )->id;

        $price = $this->price(
            $season,
            $admin
        );

        $quantity =
            $overrides['quantity_kg']
            ?? 100;

        $amount =
            $overrides['total_amount']
            ?? $quantity * 1000;

        return CoffeePurchase::create(
            array_merge(
                [
                    'coffee_season_id' =>
                        $season->id,

                    'coffee_price_id' =>
                        $price->id,

                    'agent_id' =>
                        $agent->id,

                    'farmer_id' =>
                        $farmerId,

                    'collection_point_id' =>
                        $point->id,

                    'coffee_type' =>
                        CoffeePrice::TYPE_PARCHMENT,

                    'quantity_kg' =>
                        $quantity,

                    'price_per_kg' =>
                        1000,

                    'total_amount' =>
                        $amount,

                    'currency' =>
                        'RWF',

                    'purchase_date' =>
                        now()->toDateString(),

                    'status' =>
                        CoffeePurchase::STATUS_APPROVED,

                    'approved_by' =>
                        $admin->id,

                    'approved_at' =>
                        now(),

                    'created_by' =>
                        $admin->id,
                ],
                $overrides
            )
        );
    }

    private function agent(): array
    {
        $user = $this->user('agent');

        $agent = Agent::create([
            'agent_code' =>
                sprintf(
                    'AGT-%06d',
                    $user->id
                ),

            'user_id' =>
                $user->id,

            'status' =>
                Agent::STATUS_ACTIVE,

            'created_by' =>
                $user->id,
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
                    ->numerify(
                        '078#######'
                    ),

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
        User $admin,
        string $status =
            CoffeeSeason::STATUS_ACTIVE
    ): CoffeeSeason {
        $suffix = fake()
            ->unique()
            ->numerify('######');

        return CoffeeSeason::create([
            'name' =>
                "Test Coffee Season {$suffix}",

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

            'status' => $status,

            'created_by' =>
                $admin->id,

            'activated_by' =>
                $status ===
                CoffeeSeason::STATUS_ACTIVE
                    ? $admin->id
                    : null,

            'activated_at' =>
                $status ===
                CoffeeSeason::STATUS_ACTIVE
                    ? now()
                    : null,
        ]);
    }

    private function price(
        CoffeeSeason $season,
        User $admin
    ): CoffeePrice {
        $effectiveFrom = now()
            ->subWeek()
            ->toDateString();

        $existing = CoffeePrice::query()
            ->where(
                'coffee_season_id',
                $season->id
            )
            ->where(
                'coffee_type',
                CoffeePrice::TYPE_PARCHMENT
            )
            ->whereDate(
                'effective_from',
                $effectiveFrom
            )
            ->first();

        if ($existing) {
            return $existing;
        }

        $suffix = fake()
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
                $effectiveFrom,

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

    private function location(): array
    {
        $suffix = fake()
            ->unique()
            ->numerify('######');

        $provinceId = DB::table(
            'provinces'
        )->insertGetId([
            'name' =>
                "Province {$suffix}",
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $districtId = DB::table(
            'districts'
        )->insertGetId([
            'province_id' =>
                $provinceId,

            'name' =>
                "District {$suffix}",

            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sectorId = DB::table(
            'sectors'
        )->insertGetId([
            'district_id' =>
                $districtId,

            'name' =>
                "Sector {$suffix}",

            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cellId = DB::table(
            'cells'
        )->insertGetId([
            'sector_id' =>
                $sectorId,

            'name' =>
                "Cell {$suffix}",

            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $villageId = DB::table(
            'villages'
        )->insertGetId([
            'cell_id' =>
                $cellId,

            'name' =>
                "Village {$suffix}",

            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pointId = DB::table(
            'collection_points'
        )->insertGetId([
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

        $point = DB::table(
            'collection_points'
        )->where('id', $pointId)
            ->first();

        return [$point];
    }

    private function user(
        string $role
    ): User {
        return User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'is_active' => true,
            'must_change_password' =>
                false,
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
