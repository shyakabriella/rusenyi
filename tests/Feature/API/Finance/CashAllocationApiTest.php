<?php

namespace Tests\Feature\API\Finance;

use App\Models\Agent;
use App\Models\CashAllocation;
use App\Models\CoffeeSeason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashAllocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_admin_can_create_draft_cash_allocation_with_generated_code(): void
    {
        $admin = $this->admin();

        $season =
            $this->activeSeason(
                $admin
            );

        $agent =
            $this->agent(
                $admin
            );

        Sanctum::actingAs(
            $admin
        );

        $this->postJson(
            '/api/finance/cash-allocations',
            [
                'coffee_season_id' =>
                    $season->id,

                'agent_id' =>
                    $agent->id,

                'amount' =>
                    500000,

                'allocation_date' =>
                    now()->toDateString(),

                'reference' =>
                    'REF-001',

                'purpose' =>
                    'Coffee purchasing cash.',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.allocation_code',
                'CAL-000001'
            )
            ->assertJsonPath(
                'data.status',
                CashAllocation::STATUS_DRAFT
            )
            ->assertJsonPath(
                'data.amount',
                '500000.00'
            );

        $this->assertDatabaseHas(
            'cash_allocations',
            [
                'allocation_code' =>
                    'CAL-000001',

                'agent_id' =>
                    $agent->id,

                'coffee_season_id' =>
                    $season->id,

                'status' =>
                    CashAllocation::STATUS_DRAFT,

                'created_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_accountant_can_create_cash_allocation(): void
    {
        $admin =
            $this->admin();

        $accountant =
            $this->accountant();

        $season =
            $this->activeSeason(
                $admin
            );

        $agent =
            $this->agent(
                $admin
            );

        Sanctum::actingAs(
            $accountant
        );

        $this->postJson(
            '/api/finance/cash-allocations',
            [
                'coffee_season_id' =>
                    $season->id,

                'agent_id' =>
                    $agent->id,

                'amount' =>
                    250000,

                'allocation_date' =>
                    now()->toDateString(),
            ]
        )->assertCreated();
    }

    public function test_backend_generates_sequential_allocation_codes(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->activeSeason(
                $admin
            );

        $firstAgent =
            $this->agent(
                $admin,
                'Agent One',
                'agent1@test.com',
                '0788000011'
            );

        $secondAgent =
            $this->agent(
                $admin,
                'Agent Two',
                'agent2@test.com',
                '0788000012'
            );

        Sanctum::actingAs(
            $admin
        );

        $this->createAllocationThroughApi(
            $season,
            $firstAgent,
            100000
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.allocation_code',
                'CAL-000001'
            );

        $this->createAllocationThroughApi(
            $season,
            $secondAgent,
            200000
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.allocation_code',
                'CAL-000002'
            );
    }

    public function test_zero_or_negative_amount_is_rejected(): void
    {
        $admin =
            $this->admin();

        Sanctum::actingAs(
            $admin
        );

        $this->postJson(
            '/api/finance/cash-allocations',
            [
                'coffee_season_id' =>
                    $this
                        ->activeSeason(
                            $admin
                        )
                        ->id,

                'agent_id' =>
                    $this
                        ->agent(
                            $admin
                        )
                        ->id,

                'amount' =>
                    0,

                'allocation_date' =>
                    now()->toDateString(),
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'amount'
            );
    }

    public function test_cash_cannot_be_allocated_to_inactive_agent(): void
    {
        $admin =
            $this->admin();

        $agent =
            $this->agent(
                $admin
            );

        $agent->update([
            'status' =>
                Agent::STATUS_INACTIVE,
        ]);

        Sanctum::actingAs(
            $admin
        );

        $this->postJson(
            '/api/finance/cash-allocations',
            [
                'coffee_season_id' =>
                    $this
                        ->activeSeason(
                            $admin
                        )
                        ->id,

                'agent_id' =>
                    $agent->id,

                'amount' =>
                    100000,

                'allocation_date' =>
                    now()->toDateString(),
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'agent_id'
            );
    }

    public function test_cash_cannot_be_allocated_to_non_active_season(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_DRAFT
            );

        Sanctum::actingAs(
            $admin
        );

        $this->postJson(
            '/api/finance/cash-allocations',
            [
                'coffee_season_id' =>
                    $season->id,

                'agent_id' =>
                    $this
                        ->agent(
                            $admin
                        )
                        ->id,

                'amount' =>
                    100000,

                'allocation_date' =>
                    now()->toDateString(),
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'coffee_season_id'
            );
    }

    public function test_duplicate_reference_is_rejected(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->activeSeason(
                $admin
            );

        $first =
            $this->agent(
                $admin,
                'Agent One',
                'agent1@test.com',
                '0788000011'
            );

        $second =
            $this->agent(
                $admin,
                'Agent Two',
                'agent2@test.com',
                '0788000012'
            );

        Sanctum::actingAs(
            $admin
        );

        $payload = [
            'coffee_season_id' =>
                $season->id,

            'amount' =>
                100000,

            'allocation_date' =>
                now()->toDateString(),

            'reference' =>
                'TRANSFER-001',
        ];

        $this->postJson(
            '/api/finance/cash-allocations',
            $payload + [
                'agent_id' =>
                    $first->id,
            ]
        )->assertCreated();

        $this->postJson(
            '/api/finance/cash-allocations',
            $payload + [
                'agent_id' =>
                    $second->id,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'reference'
            );
    }

    public function test_admin_can_update_draft_cash_allocation(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->activeSeason(
                $admin
            );

        $agent =
            $this->agent(
                $admin
            );

        $allocation =
            $this->allocation(
                $admin,
                $season,
                $agent
            );

        Sanctum::actingAs(
            $admin
        );

        $this->putJson(
            "/api/finance/cash-allocations/{$allocation->id}",
            [
                'coffee_season_id' =>
                    $season->id,

                'agent_id' =>
                    $agent->id,

                'amount' =>
                    750000,

                'allocation_date' =>
                    now()->toDateString(),

                'purpose' =>
                    'Updated allocation.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.amount',
                '750000.00'
            );

        $this->assertDatabaseHas(
            'cash_allocations',
            [
                'id' =>
                    $allocation->id,

                'amount' =>
                    750000,

                'updated_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_approved_cash_allocation_cannot_be_edited(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->activeSeason(
                $admin
            );

        $agent =
            $this->agent(
                $admin
            );

        $allocation =
            $this->allocation(
                $admin,
                $season,
                $agent,
                CashAllocation::STATUS_APPROVED
            );

        Sanctum::actingAs(
            $admin
        );

        $this->putJson(
            "/api/finance/cash-allocations/{$allocation->id}",
            [
                'coffee_season_id' =>
                    $season->id,

                'agent_id' =>
                    $agent->id,

                'amount' =>
                    900000,

                'allocation_date' =>
                    now()->toDateString(),
            ]
        )->assertUnprocessable();
    }

    public function test_admin_can_approve_draft_cash_allocation(): void
    {
        $admin =
            $this->admin();

        $allocation =
            $this->allocation(
                $admin,
                $this->activeSeason(
                    $admin
                ),
                $this->agent(
                    $admin
                )
            );

        Sanctum::actingAs(
            $admin
        );

        $this->patchJson(
            "/api/finance/cash-allocations/{$allocation->id}/approve"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                CashAllocation::STATUS_APPROVED
            );

        $this->assertDatabaseHas(
            'cash_allocations',
            [
                'id' =>
                    $allocation->id,

                'status' =>
                    CashAllocation::STATUS_APPROVED,

                'approved_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_accountant_can_approve_cash_allocation(): void
    {
        $admin =
            $this->admin();

        $accountant =
            $this->accountant();

        $allocation =
            $this->allocation(
                $admin,
                $this->activeSeason(
                    $admin
                ),
                $this->agent(
                    $admin
                )
            );

        Sanctum::actingAs(
            $accountant
        );

        $this->patchJson(
            "/api/finance/cash-allocations/{$allocation->id}/approve"
        )->assertOk();

        $this->assertDatabaseHas(
            'cash_allocations',
            [
                'id' =>
                    $allocation->id,

                'approved_by' =>
                    $accountant->id,
            ]
        );
    }

    public function test_allocation_cannot_be_approved_if_agent_becomes_inactive(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->activeSeason(
                $admin
            );

        $agent =
            $this->agent(
                $admin
            );

        $allocation =
            $this->allocation(
                $admin,
                $season,
                $agent
            );

        $agent->update([
            'status' =>
                Agent::STATUS_INACTIVE,
        ]);

        Sanctum::actingAs(
            $admin
        );

        $this->patchJson(
            "/api/finance/cash-allocations/{$allocation->id}/approve"
        )->assertUnprocessable();
    }

    public function test_draft_cash_allocation_can_be_cancelled(): void
    {
        $admin =
            $this->admin();

        $allocation =
            $this->allocation(
                $admin,
                $this->activeSeason(
                    $admin
                ),
                $this->agent(
                    $admin
                )
            );

        Sanctum::actingAs(
            $admin
        );

        $this->patchJson(
            "/api/finance/cash-allocations/{$allocation->id}/cancel",
            [
                'cancellation_reason' =>
                    'Allocation entered by mistake.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                CashAllocation::STATUS_CANCELLED
            )
            ->assertJsonPath(
                'data.cancellation_reason',
                'Allocation entered by mistake.'
            );
    }

    public function test_approved_cash_allocation_can_be_cancelled_with_reason(): void
    {
        $admin =
            $this->admin();

        $allocation =
            $this->allocation(
                $admin,
                $this->activeSeason(
                    $admin
                ),
                $this->agent(
                    $admin
                ),
                CashAllocation::STATUS_APPROVED
            );

        Sanctum::actingAs(
            $admin
        );

        $this->patchJson(
            "/api/finance/cash-allocations/{$allocation->id}/cancel",
            [
                'cancellation_reason' =>
                    'Cash returned before use.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                CashAllocation::STATUS_CANCELLED
            );
    }

    public function test_cancelled_cash_allocation_cannot_be_approved(): void
    {
        $admin =
            $this->admin();

        $allocation =
            $this->allocation(
                $admin,
                $this->activeSeason(
                    $admin
                ),
                $this->agent(
                    $admin
                ),
                CashAllocation::STATUS_CANCELLED
            );

        Sanctum::actingAs(
            $admin
        );

        $this->patchJson(
            "/api/finance/cash-allocations/{$allocation->id}/approve"
        )->assertUnprocessable();
    }

    public function test_admin_can_search_filter_and_paginate_cash_allocations(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->activeSeason(
                $admin
            );

        $agent =
            $this->agent(
                $admin
            );

        $this->allocation(
            $admin,
            $season,
            $agent,
            CashAllocation::STATUS_APPROVED,
            'CAL-000001',
            500000
        );

        Sanctum::actingAs(
            $admin
        );

        $url =
            '/api/finance/cash-allocations'
            . '?search=CAL-000001'
            . '&status=approved'
            . '&coffee_season_id='
            . $season->id
            . '&agent_id='
            . $agent->id
            . '&per_page=10';

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.items.0.allocation_code',
                'CAL-000001'
            );
    }

    public function test_summary_returns_allocation_amounts_by_status(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->activeSeason(
                $admin
            );

        $agent =
            $this->agent(
                $admin
            );

        $this->allocation(
            $admin,
            $season,
            $agent,
            CashAllocation::STATUS_DRAFT,
            'CAL-000001',
            100000
        );

        $this->allocation(
            $admin,
            $season,
            $agent,
            CashAllocation::STATUS_APPROVED,
            'CAL-000002',
            500000
        );

        $this->allocation(
            $admin,
            $season,
            $agent,
            CashAllocation::STATUS_CANCELLED,
            'CAL-000003',
            75000
        );

        Sanctum::actingAs(
            $admin
        );

        $this->getJson(
            '/api/finance/cash-allocations/summary'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.total_records',
                3
            )
            ->assertJsonPath(
                'data.draft_amount',
                '100000.00'
            )
            ->assertJsonPath(
                'data.approved_amount',
                '500000.00'
            )
            ->assertJsonPath(
                'data.cancelled_amount',
                '75000.00'
            );
    }

    public function test_non_finance_role_cannot_manage_cash_allocations(): void
    {
        $admin =
            $this->admin();

        $agentUser =
            $this->agentUser();

        Sanctum::actingAs(
            $agentUser
        );

        $this->getJson(
            '/api/finance/cash-allocations'
        )->assertForbidden();

        $this->postJson(
            '/api/finance/cash-allocations',
            [
                'coffee_season_id' =>
                    $this
                        ->activeSeason(
                            $admin
                        )
                        ->id,

                'agent_id' =>
                    $this
                        ->agent(
                            $admin,
                            'Second Agent',
                            'second@test.com',
                            '0788000020'
                        )
                        ->id,

                'amount' =>
                    100000,

                'allocation_date' =>
                    now()->toDateString(),
            ]
        )->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_cash_allocations(): void
    {
        $this->getJson(
            '/api/finance/cash-allocations'
        )->assertUnauthorized();
    }

    private function createAllocationThroughApi(
        CoffeeSeason $season,
        Agent $agent,
        float $amount
    ) {
        return $this->postJson(
            '/api/finance/cash-allocations',
            [
                'coffee_season_id' =>
                    $season->id,

                'agent_id' =>
                    $agent->id,

                'amount' =>
                    $amount,

                'allocation_date' =>
                    now()->toDateString(),
            ]
        );
    }

    private function allocation(
        User $creator,
        CoffeeSeason $season,
        Agent $agent,
        string $status =
            CashAllocation::STATUS_DRAFT,
        string $code =
            'CAL-000001',
        float $amount =
            100000
    ): CashAllocation {
        return CashAllocation::create([
            'allocation_code' =>
                $code,

            'coffee_season_id' =>
                $season->id,

            'agent_id' =>
                $agent->id,

            'amount' =>
                $amount,

            'currency' =>
                'RWF',

            'allocation_date' =>
                now()->toDateString(),

            'status' =>
                $status,

            'created_by' =>
                $creator->id,

            'approved_by' =>
                $status ===
                CashAllocation::STATUS_APPROVED
                    ? $creator->id
                    : null,

            'approved_at' =>
                $status ===
                CashAllocation::STATUS_APPROVED
                    ? now()
                    : null,

            'cancelled_by' =>
                $status ===
                CashAllocation::STATUS_CANCELLED
                    ? $creator->id
                    : null,

            'cancelled_at' =>
                $status ===
                CashAllocation::STATUS_CANCELLED
                    ? now()
                    : null,

            'cancellation_reason' =>
                $status ===
                CashAllocation::STATUS_CANCELLED
                    ? 'Test cancellation.'
                    : null,
        ]);
    }

    private function activeSeason(
        User $admin
    ): CoffeeSeason {
        return $this->season(
            $admin,
            CoffeeSeason::STATUS_ACTIVE
        );
    }

    private function season(
        User $admin,
        string $status
    ): CoffeeSeason {
        return CoffeeSeason::create([
            'name' =>
                'Coffee Season 2026',

            'code' =>
                'CS-2026-001',

            'start_date' =>
                now()
                    ->subMonth()
                    ->toDateString(),

            'end_date' =>
                now()
                    ->addMonths(4)
                    ->toDateString(),

            'status' =>
                $status,

            'description' =>
                'Test coffee season.',

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

    private function agent(
        User $admin,
        string $name =
            'Field Agent',
        string $email =
            'agent@test.com',
        string $phone =
            '0788000010'
    ): Agent {
        $user =
            $this->agentUser(
                $name,
                $email,
                $phone
            );

        return Agent::create([
            'agent_code' =>
                'AGT-' .
                str_pad(
                    (string)
                    $user->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),

            'user_id' =>
                $user->id,

            'status' =>
                Agent::STATUS_ACTIVE,

            'created_by' =>
                $admin->id,
        ]);
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

    private function accountant(): User
    {
        return User::factory()
            ->accountant()
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

    private function createRoles(): void
    {
        $roles = [
            [
                'admin',
                'Admin',
            ],

            [
                'accountant',
                'Accountant',
            ],

            [
                'balance',
                'Balance Officer',
            ],

            [
                'agent',
                'Agent',
            ],

            [
                'driver',
                'Driver',
            ],

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
