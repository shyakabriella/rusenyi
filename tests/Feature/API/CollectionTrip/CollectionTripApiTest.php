<?php

namespace Tests\Feature\API\CollectionTrip;

use App\Models\Agent;
use App\Models\AgentCollection;
use App\Models\CoffeeSeason;
use App\Models\CollectionTrip;
use App\Models\FieldWeighing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectionTripApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_admin_can_create_planned_collection_trip(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');
        $driver = $this->user('driver');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                500
            );

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/collection-trips',
            [
                'field_weighing_id' =>
                    $weighing->id,

                'driver_user_id' =>
                    $driver->id,

                'vehicle_registration' =>
                    'RAB 123 C',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.trip_code',
                'TRIP-000001'
            )
            ->assertJsonPath(
                'data.field_weight_kg',
                '500.00'
            )
            ->assertJsonPath(
                'data.status',
                CollectionTrip::STATUS_PLANNED
            )
            ->assertJsonPath(
                'data.driver.id',
                $driver->id
            );
    }

    public function test_balance_officer_can_create_collection_trip(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                400
            );

        Sanctum::actingAs($balance);

        $this->postJson(
            '/api/collection-trips',
            [
                'field_weighing_id' =>
                    $weighing->id,
            ]
        )->assertCreated();
    }

    public function test_unconfirmed_field_weighing_cannot_create_trip(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                400
            );

        $weighing->update([
            'status' =>
                FieldWeighing::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/collection-trips',
            [
                'field_weighing_id' =>
                    $weighing->id,
            ]
        )->assertUnprocessable();
    }

    public function test_field_weighing_cannot_have_two_active_trips(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                300
            );

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/collection-trips',
            [
                'field_weighing_id' =>
                    $weighing->id,
            ]
        )->assertCreated();

        $this->postJson(
            '/api/collection-trips',
            [
                'field_weighing_id' =>
                    $weighing->id,
            ]
        )->assertUnprocessable();
    }

    public function test_cancelled_trip_allows_new_trip(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                300
            );

        $trip =
            $this->trip(
                $weighing,
                $admin
            );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/collection-trips/{$trip->id}/cancel",
            [
                'cancellation_reason' =>
                    'Wrong vehicle assignment',
            ]
        )->assertOk();

        $this->postJson(
            '/api/collection-trips',
            [
                'field_weighing_id' =>
                    $weighing->id,
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.trip_code',
                'TRIP-000002'
            );
    }

    public function test_non_driver_user_cannot_be_assigned_as_driver(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');
        $accountant =
            $this->user('accountant');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                300
            );

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/collection-trips',
            [
                'field_weighing_id' =>
                    $weighing->id,

                'driver_user_id' =>
                    $accountant->id,
            ]
        )->assertUnprocessable();
    }

    public function test_admin_can_update_planned_trip(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');
        $driver = $this->user('driver');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                400
            );

        $trip =
            $this->trip(
                $weighing,
                $admin
            );

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/collection-trips/{$trip->id}",
            [
                'driver_user_id' =>
                    $driver->id,

                'vehicle_registration' =>
                    'RAC 555 D',

                'notes' =>
                    'Ready for transport',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.driver.id',
                $driver->id
            )
            ->assertJsonPath(
                'data.vehicle_registration',
                'RAC 555 D'
            );
    }

    public function test_started_trip_cannot_be_edited(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');
        $driver = $this->user('driver');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                400
            );

        $trip =
            $this->trip(
                $weighing,
                $admin,
                $driver
            );

        $trip->update([
            'status' =>
                CollectionTrip::STATUS_IN_TRANSIT,

            'departure_at' =>
                now(),
        ]);

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/collection-trips/{$trip->id}",
            [
                'vehicle_registration' =>
                    'OTHER',
            ]
        )->assertUnprocessable();
    }

    public function test_assigned_driver_can_start_trip(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');
        $driver = $this->user('driver');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                400
            );

        $trip =
            $this->trip(
                $weighing,
                $admin,
                $driver
            );

        Sanctum::actingAs($driver);

        $this->patchJson(
            "/api/collection-trips/{$trip->id}/start"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                CollectionTrip::STATUS_IN_TRANSIT
            )
            ->assertJsonPath(
                'data.starter.id',
                $driver->id
            );
    }

    public function test_another_driver_cannot_start_trip(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $driverA =
            $this->user('driver');

        $driverB =
            $this->user('driver');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                400
            );

        $trip =
            $this->trip(
                $weighing,
                $admin,
                $driverA
            );

        Sanctum::actingAs($driverB);

        $this->patchJson(
            "/api/collection-trips/{$trip->id}/start"
        )->assertForbidden();
    }

    public function test_assigned_driver_can_mark_trip_arrived(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');
        $driver = $this->user('driver');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                400
            );

        $trip =
            $this->trip(
                $weighing,
                $admin,
                $driver
            );

        $trip->update([
            'status' =>
                CollectionTrip::STATUS_IN_TRANSIT,

            'departure_at' =>
                now()->subHour(),

            'started_by' =>
                $driver->id,
        ]);

        Sanctum::actingAs($driver);

        $this->patchJson(
            "/api/collection-trips/{$trip->id}/arrive"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                CollectionTrip::STATUS_ARRIVED
            )
            ->assertJsonPath(
                'data.arriver.id',
                $driver->id
            );
    }

    public function test_planned_trip_cannot_be_marked_arrived(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');
        $driver = $this->user('driver');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                400
            );

        $trip =
            $this->trip(
                $weighing,
                $admin,
                $driver
            );

        Sanctum::actingAs($driver);

        $this->patchJson(
            "/api/collection-trips/{$trip->id}/arrive"
        )->assertUnprocessable();
    }

    public function test_admin_can_complete_arrived_trip(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');
        $driver = $this->user('driver');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                500
            );

        $trip =
            $this->trip(
                $weighing,
                $admin,
                $driver
            );

        $trip->update([
            'status' =>
                CollectionTrip::STATUS_ARRIVED,

            'departure_at' =>
                now()->subHours(2),

            'arrived_at' =>
                now()->subHour(),

            'started_by' =>
                $driver->id,

            'arrived_by' =>
                $driver->id,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/collection-trips/{$trip->id}/complete"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                CollectionTrip::STATUS_COMPLETED
            )
            ->assertJsonPath(
                'data.completer.id',
                $admin->id
            );
    }

    public function test_planned_trip_cannot_be_completed(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $weighing =
            $this->confirmedWeighing(
                $admin,
                $balance,
                500
            );

        $trip =
            $this->trip(
                $weighing,
                $admin
            );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/collection-trips/{$trip->id}/complete"
        )->assertUnprocessable();
    }

    public function test_driver_sees_only_assigned_trips(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $driverA =
            $this->user('driver');

        $driverB =
            $this->user('driver');

        $first =
            $this->confirmedWeighing(
                $admin,
                $balance,
                300
            );

        $second =
            $this->confirmedWeighing(
                $admin,
                $balance,
                400
            );

        $this->trip(
            $first,
            $admin,
            $driverA
        );

        $this->trip(
            $second,
            $admin,
            $driverB
        );

        Sanctum::actingAs($driverA);

        $this->getJson(
            '/api/collection-trips'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.items.0.driver_user_id',
                $driverA->id
            );
    }

    public function test_agent_sees_only_own_collection_trips(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$agentA, $agentUserA] =
            $this->agent($admin);

        [$agentB] =
            $this->agent($admin);

        $first =
            $this->confirmedWeighing(
                $admin,
                $balance,
                300,
                $agentA
            );

        $second =
            $this->confirmedWeighing(
                $admin,
                $balance,
                400,
                $agentB
            );

        $this->trip(
            $first,
            $admin
        );

        $this->trip(
            $second,
            $admin
        );

        Sanctum::actingAs(
            $agentUserA
        );

        $this->getJson(
            '/api/collection-trips'
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

    public function test_store_cannot_access_collection_trips(): void
    {
        Sanctum::actingAs(
            $this->user('store')
        );

        $this->getJson(
            '/api/collection-trips'
        )->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_collection_trips(): void
    {
        $this->getJson(
            '/api/collection-trips'
        )->assertUnauthorized();
    }

    public function test_summary_returns_trip_counts_and_completed_weight(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        $first =
            $this->confirmedWeighing(
                $admin,
                $balance,
                300
            );

        $second =
            $this->confirmedWeighing(
                $admin,
                $balance,
                500
            );

        $this->trip(
            $first,
            $admin
        );

        $completed =
            $this->trip(
                $second,
                $admin
            );

        $completed->update([
            'status' =>
                CollectionTrip::STATUS_COMPLETED,

            'completed_by' =>
                $admin->id,

            'completed_at' =>
                now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/collection-trips/summary'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.total_trips',
                2
            )
            ->assertJsonPath(
                'data.planned_trips',
                1
            )
            ->assertJsonPath(
                'data.completed_trips',
                1
            )
            ->assertJsonPath(
                'data.completed_weight_kg',
                '500.00'
            );
    }

    private function trip(
        FieldWeighing $weighing,
        User $creator,
        ?User $driver = null
    ): CollectionTrip {
        return CollectionTrip::create([
            'coffee_season_id' =>
                $weighing->coffee_season_id,

            'agent_collection_id' =>
                $weighing->agent_collection_id,

            'field_weighing_id' =>
                $weighing->id,

            'agent_id' =>
                $weighing->agent_id,

            'collection_point_id' =>
                $weighing->collection_point_id,

            'driver_user_id' =>
                $driver?->id,

            'vehicle_registration' =>
                $driver
                    ? 'RAB 123 C'
                    : null,

            'field_weight_kg' =>
                $weighing->field_weight_kg,

            'status' =>
                CollectionTrip::STATUS_PLANNED,

            'created_by' =>
                $creator->id,
        ]);
    }

    private function confirmedWeighing(
        User $admin,
        User $balance,
        float $weight,
        ?Agent $agent = null
    ): FieldWeighing {
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
                    null,

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

        return FieldWeighing::create([
            'coffee_season_id' =>
                $season->id,

            'agent_collection_id' =>
                $collection->id,

            'agent_id' =>
                $agent->id,

            'collection_point_id' =>
                null,

            'balance_officer_id' =>
                $balance->id,

            'expected_quantity_kg' =>
                $weight,

            'field_weight_kg' =>
                $weight,

            'difference_kg' =>
                0,

            'difference_percentage' =>
                0,

            'weighed_at' =>
                now()->subHour(),

            'status' =>
                FieldWeighing::STATUS_CONFIRMED,

            'created_by' =>
                $balance->id,

            'confirmed_by' =>
                $balance->id,

            'confirmed_at' =>
                now()->subHour(),
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
                "Trip Season {$suffix}",

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

    private function user(
        string $role
    ): User {
        return User::factory()->create([
            'role' =>
                $role,

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
