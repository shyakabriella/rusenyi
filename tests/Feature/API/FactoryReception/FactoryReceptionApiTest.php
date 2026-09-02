<?php

namespace Tests\Feature\API\FactoryReception;

use App\Models\Agent;
use App\Models\AgentCollection;
use App\Models\CoffeeSeason;
use App\Models\FactoryReception;
use App\Models\FieldWeighing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FactoryReceptionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_balance_officer_can_create_draft_factory_reception(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$weighing] = $this->confirmedWeighing(
            $admin,
            $balance,
            820,
            817
        );

        Sanctum::actingAs($balance);

        $this->postJson('/api/factory-receptions', [
            'field_weighing_id' => $weighing->id,
            'factory_weight_kg' => 814,
            'bag_count' => 12,
            'received_at' => now()->toDateTimeString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.reception_code', 'FR-000001')
            ->assertJsonPath('data.field_weight_kg', '817.00')
            ->assertJsonPath('data.factory_weight_kg', '814.00')
            ->assertJsonPath('data.difference_kg', '-3.00')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath(
                'data.balance_officer_id',
                $balance->id
            );
    }

    public function test_backend_generates_sequential_reception_codes(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$first] = $this->confirmedWeighing(
            $admin,
            $balance,
            400,
            398
        );

        [$second] = $this->confirmedWeighing(
            $admin,
            $balance,
            300,
            299
        );

        Sanctum::actingAs($balance);

        $this->postJson(
            '/api/factory-receptions',
            $this->payload($first, 397)
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.reception_code',
                'FR-000001'
            );

        $this->postJson(
            '/api/factory-receptions',
            $this->payload($second, 298)
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.reception_code',
                'FR-000002'
            );
    }

    public function test_field_weight_is_taken_from_confirmed_field_weighing(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$weighing] = $this->confirmedWeighing(
            $admin,
            $balance,
            500,
            495
        );

        Sanctum::actingAs($balance);

        $this->postJson('/api/factory-receptions', [
            'field_weighing_id' => $weighing->id,
            'field_weight_kg' => 999999,
            'factory_weight_kg' => 493,
            'received_at' => now()->toDateTimeString(),
        ])
            ->assertCreated()
            ->assertJsonPath(
                'data.field_weight_kg',
                '495.00'
            )
            ->assertJsonPath(
                'data.difference_kg',
                '-2.00'
            );
    }

    public function test_unconfirmed_field_weighing_cannot_be_received(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$weighing] = $this->confirmedWeighing(
            $admin,
            $balance,
            300,
            299
        );

        $weighing->update([
            'status' => FieldWeighing::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($balance);

        $this->postJson(
            '/api/factory-receptions',
            $this->payload($weighing, 298)
        )->assertUnprocessable();
    }

    public function test_field_weighing_cannot_have_two_active_receptions(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$weighing] = $this->confirmedWeighing(
            $admin,
            $balance,
            300,
            299
        );

        Sanctum::actingAs($balance);

        $this->postJson(
            '/api/factory-receptions',
            $this->payload($weighing, 298)
        )->assertCreated();

        $this->postJson(
            '/api/factory-receptions',
            $this->payload($weighing, 297)
        )->assertUnprocessable();
    }

    public function test_cancelled_reception_allows_corrected_reception(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$weighing] = $this->confirmedWeighing(
            $admin,
            $balance,
            400,
            399
        );

        Sanctum::actingAs($balance);

        $first = $this->postJson(
            '/api/factory-receptions',
            $this->payload($weighing, 390)
        )
            ->assertCreated()
            ->json('data');

        $this->patchJson(
            "/api/factory-receptions/{$first['id']}/cancel",
            [
                'cancellation_reason' =>
                    'Factory scale reading was incorrect',
            ]
        )->assertOk();

        $this->postJson(
            '/api/factory-receptions',
            $this->payload($weighing, 398)
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.reception_code',
                'FR-000002'
            );
    }

    public function test_balance_officer_can_update_own_draft_reception(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$weighing] = $this->confirmedWeighing(
            $admin,
            $balance,
            500,
            495
        );

        $reception = $this->reception(
            $weighing,
            $balance,
            490
        );

        Sanctum::actingAs($balance);

        $this->putJson(
            "/api/factory-receptions/{$reception->id}",
            [
                'factory_weight_kg' => 493,
                'bag_count' => 8,
                'received_at' => now()->toDateTimeString(),
                'notes' => 'Factory weight checked again',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.factory_weight_kg',
                '493.00'
            )
            ->assertJsonPath(
                'data.difference_kg',
                '-2.00'
            );
    }

    public function test_balance_officer_cannot_update_another_officers_reception(): void
    {
        $admin = $this->user('admin');
        $balanceA = $this->user('balance');
        $balanceB = $this->user('balance');

        [$weighing] = $this->confirmedWeighing(
            $admin,
            $balanceA,
            400,
            399
        );

        $reception = $this->reception(
            $weighing,
            $balanceA,
            398
        );

        Sanctum::actingAs($balanceB);

        $this->putJson(
            "/api/factory-receptions/{$reception->id}",
            [
                'factory_weight_kg' => 399,
                'received_at' => now()->toDateTimeString(),
            ]
        )->assertForbidden();
    }

    public function test_balance_officer_can_confirm_own_reception(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$weighing] = $this->confirmedWeighing(
            $admin,
            $balance,
            600,
            598
        );

        $reception = $this->reception(
            $weighing,
            $balance,
            596
        );

        Sanctum::actingAs($balance);

        $this->patchJson(
            "/api/factory-receptions/{$reception->id}/confirm"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                FactoryReception::STATUS_CONFIRMED
            )
            ->assertJsonPath(
                'data.confirmer.id',
                $balance->id
            );
    }

    public function test_confirmed_reception_cannot_be_edited(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$weighing] = $this->confirmedWeighing(
            $admin,
            $balance,
            500,
            498
        );

        $reception = $this->reception(
            $weighing,
            $balance,
            497
        );

        $reception->update([
            'status' => FactoryReception::STATUS_CONFIRMED,
            'confirmed_by' => $balance->id,
            'confirmed_at' => now(),
        ]);

        Sanctum::actingAs($balance);

        $this->putJson(
            "/api/factory-receptions/{$reception->id}",
            [
                'factory_weight_kg' => 498,
                'received_at' => now()->toDateTimeString(),
            ]
        )->assertUnprocessable();
    }

    public function test_reception_cannot_happen_before_field_weighing(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$weighing] = $this->confirmedWeighing(
            $admin,
            $balance,
            250,
            249
        );

        Sanctum::actingAs($balance);

        $this->postJson('/api/factory-receptions', [
            'field_weighing_id' => $weighing->id,
            'factory_weight_kg' => 248,
            'received_at' => $weighing
                ->weighed_at
                ->copy()
                ->subHour()
                ->toDateTimeString(),
        ])->assertUnprocessable();
    }

    public function test_admin_and_accountant_can_view_factory_receptions(): void
    {
        $admin = $this->user('admin');
        $accountant = $this->user('accountant');
        $balance = $this->user('balance');

        [$weighing] = $this->confirmedWeighing(
            $admin,
            $balance,
            100,
            99
        );

        $this->reception(
            $weighing,
            $balance,
            98
        );

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/factory-receptions'
        )->assertOk();

        Sanctum::actingAs($accountant);

        $this->getJson(
            '/api/factory-receptions'
        )->assertOk();
    }

    public function test_admin_and_accountant_cannot_create_reception(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$weighing] = $this->confirmedWeighing(
            $admin,
            $balance,
            100,
            99
        );

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/factory-receptions',
            $this->payload($weighing, 98)
        )->assertForbidden();

        $accountant = $this->user('accountant');

        Sanctum::actingAs($accountant);

        $this->postJson(
            '/api/factory-receptions',
            $this->payload($weighing, 98)
        )->assertForbidden();
    }

    public function test_agent_can_view_only_own_receptions(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$agentA, $agentUserA] = $this->agent($admin);
        [$agentB] = $this->agent($admin);

        [$weighingA] = $this->confirmedWeighing(
            $admin,
            $balance,
            100,
            99,
            $agentA
        );

        [$weighingB] = $this->confirmedWeighing(
            $admin,
            $balance,
            200,
            198,
            $agentB
        );

        $this->reception(
            $weighingA,
            $balance,
            98
        );

        $this->reception(
            $weighingB,
            $balance,
            197
        );

        Sanctum::actingAs($agentUserA);

        $this->getJson('/api/factory-receptions')
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

    public function test_driver_and_store_cannot_access_factory_receptions(): void
    {
        foreach (['driver', 'store'] as $role) {
            Sanctum::actingAs(
                $this->user($role)
            );

            $this->getJson(
                '/api/factory-receptions'
            )->assertForbidden();
        }
    }

    public function test_unauthenticated_user_cannot_access_factory_receptions(): void
    {
        $this->getJson(
            '/api/factory-receptions'
        )->assertUnauthorized();
    }

    public function test_summary_returns_confirmed_factory_weights(): void
    {
        $admin = $this->user('admin');
        $balance = $this->user('balance');

        [$first] = $this->confirmedWeighing(
            $admin,
            $balance,
            500,
            495
        );

        [$second] = $this->confirmedWeighing(
            $admin,
            $balance,
            300,
            298
        );

        $firstReception = $this->reception(
            $first,
            $balance,
            492
        );

        $secondReception = $this->reception(
            $second,
            $balance,
            297
        );

        $firstReception->update([
            'status' => FactoryReception::STATUS_CONFIRMED,
        ]);

        $secondReception->update([
            'status' => FactoryReception::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/factory-receptions/summary'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.confirmed_records',
                2
            )
            ->assertJsonPath(
                'data.field_weight_kg',
                '793.00'
            )
            ->assertJsonPath(
                'data.factory_weight_kg',
                '789.00'
            )
            ->assertJsonPath(
                'data.difference_kg',
                '-4.00'
            )
            ->assertJsonPath(
                'data.shortage_quantity_kg',
                '4.00'
            );
    }

    private function payload(
        FieldWeighing $weighing,
        float $factoryWeight
    ): array {
        return [
            'field_weighing_id' => $weighing->id,
            'factory_weight_kg' => $factoryWeight,
            'received_at' => now()->toDateTimeString(),
        ];
    }

    private function reception(
        FieldWeighing $weighing,
        User $balance,
        float $factoryWeight
    ): FactoryReception {
        $fieldWeight = (float) $weighing->field_weight_kg;

        $difference = round(
            $factoryWeight - $fieldWeight,
            2
        );

        $percentage = $fieldWeight > 0
            ? round(
                ($difference / $fieldWeight) * 100,
                4
            )
            : 0;

        return FactoryReception::create([
            'coffee_season_id' => $weighing->coffee_season_id,
            'agent_collection_id' => $weighing->agent_collection_id,
            'field_weighing_id' => $weighing->id,
            'agent_id' => $weighing->agent_id,
            'collection_point_id' => null,
            'balance_officer_id' => $balance->id,
            'field_weight_kg' => $fieldWeight,
            'factory_weight_kg' => $factoryWeight,
            'difference_kg' => $difference,
            'difference_percentage' => $percentage,
            'received_at' => now(),
            'status' => FactoryReception::STATUS_DRAFT,
            'created_by' => $balance->id,
        ]);
    }

    private function confirmedWeighing(
        User $admin,
        User $balance,
        float $expected,
        float $fieldWeight,
        ?Agent $agent = null
    ): array {
        $season = $this->season($admin);

        if (!$agent) {
            [$agent] = $this->agent($admin);
        }

        $collection = AgentCollection::create([
            'coffee_season_id' => $season->id,
            'agent_id' => $agent->id,
            'collection_point_id' => null,
            'collection_date' => now()
                ->subDay()
                ->toDateString(),
            'status' => AgentCollection::STATUS_COMPLETED,
            'created_by' => $admin->id,
            'completed_by' => $admin->id,
            'completed_at' => now()->subHours(3),
        ]);

        $difference = round(
            $fieldWeight - $expected,
            2
        );

        $percentage = $expected > 0
            ? round(
                ($difference / $expected) * 100,
                4
            )
            : 0;

        $weighing = FieldWeighing::create([
            'coffee_season_id' => $season->id,
            'agent_collection_id' => $collection->id,
            'agent_id' => $agent->id,
            'collection_point_id' => null,
            'balance_officer_id' => $balance->id,
            'expected_quantity_kg' => $expected,
            'field_weight_kg' => $fieldWeight,
            'difference_kg' => $difference,
            'difference_percentage' => $percentage,
            'weighed_at' => now()->subHour(),
            'status' => FieldWeighing::STATUS_CONFIRMED,
            'created_by' => $balance->id,
            'confirmed_by' => $balance->id,
            'confirmed_at' => now()->subMinutes(50),
        ]);

        return [$weighing, $collection, $agent];
    }

    private function agent(User $admin): array
    {
        $user = $this->user('agent');

        $agent = Agent::create([
            'agent_code' => 'AGT-' .
                fake()
                    ->unique()
                    ->numerify('######'),
            'user_id' => $user->id,
            'status' => Agent::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);

        return [$agent, $user];
    }

    private function season(User $admin): CoffeeSeason
    {
        $suffix = fake()
            ->unique()
            ->numerify('######');

        return CoffeeSeason::create([
            'name' => "Factory Season {$suffix}",
            'code' => "CS-{$suffix}",
            'start_date' => now()
                ->subMonth()
                ->toDateString(),
            'end_date' => now()
                ->addMonths(3)
                ->toDateString(),
            'status' => CoffeeSeason::STATUS_ACTIVE,
            'created_by' => $admin->id,
            'activated_by' => $admin->id,
            'activated_at' => now(),
        ]);
    }

    private function user(string $role): User
    {
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
            DB::table('roles')->updateOrInsert(
                ['name' => $name],
                [
                    'display_name' => $displayName,
                    'description' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
