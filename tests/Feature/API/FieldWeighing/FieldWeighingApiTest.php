<?php

namespace Tests\Feature\API\FieldWeighing;

use App\Models\Agent;
use App\Models\AgentCollection;
use App\Models\CoffeePrice;
use App\Models\CoffeePurchase;
use App\Models\CoffeeSeason;
use App\Models\Farmer;
use App\Models\FieldWeighing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FieldWeighingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_balance_officer_can_create_draft_weighing(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $collection =
            $this->completedCollection(
                $admin,
                820
            );

        Sanctum::actingAs($balance);

        $this->postJson(
            '/api/field-weighings',
            [
                'agent_collection_id' =>
                    $collection->id,

                'field_weight_kg' =>
                    817,

                'bag_count' =>
                    12,

                'weighed_at' =>
                    now()->toDateTimeString(),
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.weighing_code',
                'FW-000001'
            )
            ->assertJsonPath(
                'data.balance_officer_id',
                $balance->id
            )
            ->assertJsonPath(
                'data.expected_quantity_kg',
                '820.00'
            )
            ->assertJsonPath(
                'data.field_weight_kg',
                '817.00'
            )
            ->assertJsonPath(
                'data.difference_kg',
                '-3.00'
            )
            ->assertJsonPath(
                'data.status',
                FieldWeighing::STATUS_DRAFT
            );
    }

    public function test_backend_generates_sequential_weighing_codes(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $first =
            $this->completedCollection(
                $admin,
                100
            );

        $second =
            $this->completedCollection(
                $admin,
                200
            );

        Sanctum::actingAs($balance);

        $this->postJson(
            '/api/field-weighings',
            $this->payload(
                $first,
                99
            )
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.weighing_code',
                'FW-000001'
            );

        $this->postJson(
            '/api/field-weighings',
            $this->payload(
                $second,
                198
            )
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.weighing_code',
                'FW-000002'
            );
    }

    public function test_expected_quantity_is_calculated_from_collection_purchases(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $collection =
            $this->completedCollection(
                $admin,
                550
            );

        Sanctum::actingAs($balance);

        $response = $this->postJson(
            '/api/field-weighings',
            [
                'agent_collection_id' =>
                    $collection->id,

                'expected_quantity_kg' =>
                    999999,

                'field_weight_kg' =>
                    548,

                'weighed_at' =>
                    now()->toDateTimeString(),
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.expected_quantity_kg',
                '550.00'
            )
            ->assertJsonPath(
                'data.difference_kg',
                '-2.00'
            );
    }

    public function test_open_agent_collection_cannot_be_weighed(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $collection =
            $this->completedCollection(
                $admin,
                300
            );

        $collection->update([
            'status' =>
                AgentCollection::STATUS_OPEN,
        ]);

        Sanctum::actingAs($balance);

        $this->postJson(
            '/api/field-weighings',
            $this->payload(
                $collection,
                300
            )
        )->assertUnprocessable();
    }

    public function test_collection_cannot_have_two_active_weighings(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $collection =
            $this->completedCollection(
                $admin,
                300
            );

        Sanctum::actingAs($balance);

        $this->postJson(
            '/api/field-weighings',
            $this->payload(
                $collection,
                299
            )
        )->assertCreated();

        $this->postJson(
            '/api/field-weighings',
            $this->payload(
                $collection,
                298
            )
        )->assertUnprocessable();
    }

    public function test_cancelled_weighing_allows_corrected_reweighing(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $collection =
            $this->completedCollection(
                $admin,
                300
            );

        Sanctum::actingAs($balance);

        $first = $this->postJson(
            '/api/field-weighings',
            $this->payload(
                $collection,
                290
            )
        )
            ->assertCreated()
            ->json('data');

        $this->patchJson(
            "/api/field-weighings/{$first['id']}/cancel",
            [
                'cancellation_reason' =>
                    'Scale reading entered incorrectly',
            ]
        )->assertOk();

        $this->postJson(
            '/api/field-weighings',
            $this->payload(
                $collection,
                299
            )
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.weighing_code',
                'FW-000002'
            );
    }

    public function test_balance_officer_can_update_own_draft_weighing(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $collection =
            $this->completedCollection(
                $admin,
                500
            );

        $weighing =
            $this->weighing(
                $collection,
                $balance,
                490
            );

        Sanctum::actingAs($balance);

        $this->putJson(
            "/api/field-weighings/{$weighing->id}",
            [
                'field_weight_kg' => 495,

                'bag_count' => 8,

                'weighed_at' =>
                    now()->toDateTimeString(),

                'notes' =>
                    'Weight checked again',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.field_weight_kg',
                '495.00'
            )
            ->assertJsonPath(
                'data.difference_kg',
                '-5.00'
            );
    }

    public function test_balance_officer_cannot_update_another_officers_weighing(): void
    {
        $admin = $this->user('admin');

        $balanceA =
            $this->user('balance');

        $balanceB =
            $this->user('balance');

        $collection =
            $this->completedCollection(
                $admin,
                400
            );

        $weighing =
            $this->weighing(
                $collection,
                $balanceA,
                399
            );

        Sanctum::actingAs($balanceB);

        $this->putJson(
            "/api/field-weighings/{$weighing->id}",
            [
                'field_weight_kg' => 400,

                'weighed_at' =>
                    now()->toDateTimeString(),
            ]
        )->assertForbidden();
    }

    public function test_balance_officer_can_confirm_own_weighing(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $collection =
            $this->completedCollection(
                $admin,
                600
            );

        $weighing =
            $this->weighing(
                $collection,
                $balance,
                598
            );

        Sanctum::actingAs($balance);

        $this->patchJson(
            "/api/field-weighings/{$weighing->id}/confirm"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                FieldWeighing::STATUS_CONFIRMED
            )
            ->assertJsonPath(
                'data.confirmer.id',
                $balance->id
            );
    }

    public function test_confirmed_weighing_cannot_be_edited(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $collection =
            $this->completedCollection(
                $admin,
                500
            );

        $weighing =
            $this->weighing(
                $collection,
                $balance,
                498
            );

        $weighing->update([
            'status' =>
                FieldWeighing::STATUS_CONFIRMED,

            'confirmed_by' =>
                $balance->id,

            'confirmed_at' =>
                now(),
        ]);

        Sanctum::actingAs($balance);

        $this->putJson(
            "/api/field-weighings/{$weighing->id}",
            [
                'field_weight_kg' =>
                    500,

                'weighed_at' =>
                    now()->toDateTimeString(),
            ]
        )->assertUnprocessable();
    }

    public function test_weighing_cannot_be_recorded_before_collection_date(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $collection =
            $this->completedCollection(
                $admin,
                250
            );

        Sanctum::actingAs($balance);

        $this->postJson(
            '/api/field-weighings',
            [
                'agent_collection_id' =>
                    $collection->id,

                'field_weight_kg' =>
                    249,

                'weighed_at' =>
                    now()
                        ->subDays(2)
                        ->toDateTimeString(),
            ]
        )->assertUnprocessable();
    }

    public function test_admin_and_accountant_can_view_field_weighings(): void
    {
        $admin = $this->user('admin');
        $accountant =
            $this->user('accountant');

        $balance = $this->user('balance');

        $collection =
            $this->completedCollection(
                $admin,
                100
            );

        $this->weighing(
            $collection,
            $balance,
            99
        );

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/field-weighings'
        )->assertOk();

        Sanctum::actingAs($accountant);

        $this->getJson(
            '/api/field-weighings'
        )->assertOk();
    }

    public function test_admin_and_accountant_cannot_create_field_weighing(): void
    {
        $admin = $this->user('admin');

        $collection =
            $this->completedCollection(
                $admin,
                100
            );

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/field-weighings',
            $this->payload(
                $collection,
                99
            )
        )->assertForbidden();

        $accountant =
            $this->user('accountant');

        Sanctum::actingAs($accountant);

        $this->postJson(
            '/api/field-weighings',
            $this->payload(
                $collection,
                99
            )
        )->assertForbidden();
    }

    public function test_agent_can_view_only_own_field_weighings(): void
    {
        $admin = $this->user('admin');

        [$agentA, $agentUserA] =
            $this->agent($admin);

        [$agentB] =
            $this->agent($admin);

        $balance =
            $this->user('balance');

        $collectionA =
            $this->completedCollection(
                $admin,
                100,
                $agentA
            );

        $collectionB =
            $this->completedCollection(
                $admin,
                200,
                $agentB
            );

        $this->weighing(
            $collectionA,
            $balance,
            99
        );

        $this->weighing(
            $collectionB,
            $balance,
            198
        );

        Sanctum::actingAs($agentUserA);

        $this->getJson(
            '/api/field-weighings'
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

    public function test_driver_and_store_cannot_access_field_weighings(): void
    {
        foreach ([
            'driver',
            'store',
        ] as $role) {
            Sanctum::actingAs(
                $this->user($role)
            );

            $this->getJson(
                '/api/field-weighings'
            )->assertForbidden();
        }
    }

    public function test_unauthenticated_user_cannot_access_field_weighings(): void
    {
        $this->getJson(
            '/api/field-weighings'
        )->assertUnauthorized();
    }

    public function test_summary_returns_confirmed_weight_totals(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $first =
            $this->completedCollection(
                $admin,
                500
            );

        $second =
            $this->completedCollection(
                $admin,
                300
            );

        $firstWeighing =
            $this->weighing(
                $first,
                $balance,
                495
            );

        $secondWeighing =
            $this->weighing(
                $second,
                $balance,
                298
            );

        $firstWeighing->update([
            'status' =>
                FieldWeighing::STATUS_CONFIRMED,
        ]);

        $secondWeighing->update([
            'status' =>
                FieldWeighing::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/field-weighings/summary'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.confirmed_records',
                2
            )
            ->assertJsonPath(
                'data.expected_quantity_kg',
                '800.00'
            )
            ->assertJsonPath(
                'data.field_weight_kg',
                '793.00'
            )
            ->assertJsonPath(
                'data.difference_kg',
                '-7.00'
            )
            ->assertJsonPath(
                'data.shortage_quantity_kg',
                '7.00'
            );
    }

    private function payload(
        AgentCollection $collection,
        float $weight
    ): array {
        return [
            'agent_collection_id' =>
                $collection->id,

            'field_weight_kg' =>
                $weight,

            'weighed_at' =>
                now()->toDateTimeString(),
        ];
    }

    private function weighing(
        AgentCollection $collection,
        User $balance,
        float $weight
    ): FieldWeighing {
        $expected = (float)
            $collection
                ->purchases()
                ->where(
                    'status',
                    CoffeePurchase::STATUS_APPROVED
                )
                ->sum('quantity_kg');

        $difference =
            $weight - $expected;

        $percentage =
            $expected > 0
                ? (
                    $difference /
                    $expected
                ) * 100
                : 0;

        return FieldWeighing::create([
            'coffee_season_id' =>
                $collection
                    ->coffee_season_id,

            'agent_collection_id' =>
                $collection->id,

            'agent_id' =>
                $collection->agent_id,

            'collection_point_id' =>
                $collection
                    ->collection_point_id,

            'balance_officer_id' =>
                $balance->id,

            'expected_quantity_kg' =>
                $expected,

            'field_weight_kg' =>
                $weight,

            'difference_kg' =>
                round(
                    $difference,
                    2
                ),

            'difference_percentage' =>
                round(
                    $percentage,
                    4
                ),

            'weighed_at' =>
                now(),

            'status' =>
                FieldWeighing::STATUS_DRAFT,

            'created_by' =>
                $balance->id,
        ]);
    }

    private function completedCollection(
        User $admin,
        float $quantity,
        ?Agent $agent = null
    ): AgentCollection {
        [$point] =
            $this->location();

        $season =
            $this->season($admin);

        if (!$agent) {
            [$agent] =
                $this->agent($admin);
        }

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
                    now(),
            ]);

        $this->purchase(
            $agent,
            $season,
            $admin,
            $point,
            $quantity
        )->update([
            'agent_collection_id' =>
                $collection->id,
        ]);

        return $collection;
    }

    private function purchase(
        Agent $agent,
        CoffeeSeason $season,
        User $admin,
        $point,
        float $quantity
    ): CoffeePurchase {
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

        return CoffeePurchase::create([
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
                now(),

            'created_by' =>
                $admin->id,
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
                        ->numerify(
                            '######'
                        ),

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
                    ->numerify(
                        '######'
                    ),

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
        User $admin
    ): CoffeeSeason {
        $suffix =
            fake()
                ->unique()
                ->numerify(
                    '######'
                );

        return CoffeeSeason::create([
            'name' =>
                "Field Season {$suffix}",

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
                ->numerify(
                    '######'
                );

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

    private function location(): array
    {
        $suffix =
            fake()
                ->unique()
                ->numerify(
                    '######'
                );

        $provinceId =
            DB::table(
                'provinces'
            )->insertGetId([
                'name' =>
                    "Province {$suffix}",

                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $districtId =
            DB::table(
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

        $sectorId =
            DB::table(
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

        $cellId =
            DB::table(
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

        $villageId =
            DB::table(
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

        $pointId =
            DB::table(
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

        return [
            DB::table(
                'collection_points'
            )
                ->where(
                    'id',
                    $pointId
                )
                ->first(),
        ];
    }

    private function user(
        string $role
    ): User {
        return User::factory()->create([
            'role' => $role,

            'status' =>
                'active',

            'is_active' =>
                true,

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
                    [
                        'name' =>
                            $name,
                    ],
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
