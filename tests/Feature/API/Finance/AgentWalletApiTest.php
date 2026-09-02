<?php

namespace Tests\Feature\API\Finance;

use App\Models\Agent;
use App\Models\AgentWalletTransaction;
use App\Models\CashAllocation;
use App\Models\CoffeeSeason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentWalletApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_admin_can_list_agent_wallets(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        $season = $this->season($admin);

        $this->credit(
            $agent,
            $season,
            500000,
            $admin
        );

        Sanctum::actingAs($admin);

        $this->getJson('/api/finance/agent-wallets')
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.agent.id',
                $agent->id
            )
            ->assertJsonPath(
                'data.items.0.summary.balance',
                '500000.00'
            );
    }

    public function test_accountant_can_view_agent_wallet(): void
    {
        $admin = $this->admin();
        $accountant = User::factory()
            ->accountant()
            ->create();

        $agent = $this->agent();
        $season = $this->season($admin);

        $this->credit(
            $agent,
            $season,
            250000,
            $admin
        );

        Sanctum::actingAs($accountant);

        $this->getJson(
            "/api/finance/agent-wallets/{$agent->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.balance',
                '250000.00'
            );
    }

    public function test_agent_cannot_access_finance_wallet_list(): void
    {
        $agent = $this->agent();

        Sanctum::actingAs($agent->user);

        $this->getJson('/api/finance/agent-wallets')
            ->assertForbidden();
    }

    public function test_agent_can_view_only_own_wallet(): void
    {
        $admin = $this->admin();
        $season = $this->season($admin);

        $firstAgent = $this->agent();
        $secondAgent = $this->agent();

        $this->credit(
            $firstAgent,
            $season,
            100000,
            $admin
        );

        $this->credit(
            $secondAgent,
            $season,
            900000,
            $admin
        );

        Sanctum::actingAs($firstAgent->user);

        $this->getJson('/api/agent/wallet')
            ->assertOk()
            ->assertJsonPath(
                'data.agent.id',
                $firstAgent->id
            )
            ->assertJsonPath(
                'data.summary.balance',
                '100000.00'
            );
    }

    public function test_agent_can_view_own_wallet_transactions(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        $season = $this->season($admin);

        $transaction = $this->credit(
            $agent,
            $season,
            300000,
            $admin
        );

        Sanctum::actingAs($agent->user);

        $this->getJson('/api/agent/wallet/transactions')
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.transaction_code',
                $transaction->transaction_code
            )
            ->assertJsonPath(
                'data.items.0.direction',
                'credit'
            );
    }

    public function test_finance_summary_returns_available_balance(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        $season = $this->season($admin);

        $this->credit(
            $agent,
            $season,
            600000,
            $admin
        );

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/finance/agent-wallets/summary'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.total_allocated',
                '600000.00'
            )
            ->assertJsonPath(
                'data.available_balance',
                '600000.00'
            );
    }

    public function test_approving_allocation_creates_wallet_credit(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        $season = $this->season($admin);

        $allocation = CashAllocation::create([
            'allocation_code' => 'CAL-000001',
            'coffee_season_id' => $season->id,
            'agent_id' => $agent->id,
            'amount' => 400000,
            'currency' => 'RWF',
            'payment_method' => 'cash',
            'allocation_date' => now()->toDateString(),
            'status' => CashAllocation::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseCount(
            'agent_wallet_transactions',
            0
        );

        $allocation->update([
            'status' => CashAllocation::STATUS_APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->assertDatabaseHas(
            'agent_wallet_transactions',
            [
                'agent_id' => $agent->id,
                'type' => AgentWalletTransaction::TYPE_CASH_ALLOCATION,
                'direction' => AgentWalletTransaction::DIRECTION_CREDIT,
                'amount' => 400000,
            ]
        );
    }

    public function test_cancelling_approved_allocation_reverses_wallet_credit(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        $season = $this->season($admin);

        $allocation = CashAllocation::create([
            'allocation_code' => 'CAL-000001',
            'coffee_season_id' => $season->id,
            'agent_id' => $agent->id,
            'amount' => 700000,
            'currency' => 'RWF',
            'payment_method' => 'cash',
            'allocation_date' => now()->toDateString(),
            'status' => CashAllocation::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);

        $allocation->update([
            'status' => CashAllocation::STATUS_APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $allocation->update([
            'status' => CashAllocation::STATUS_CANCELLED,
            'cancelled_by' => $admin->id,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Wrong allocation',
        ]);

        $this->assertDatabaseHas(
            'agent_wallet_transactions',
            [
                'agent_id' => $agent->id,
                'type' => AgentWalletTransaction::TYPE_CASH_ALLOCATION_REVERSAL,
                'direction' => AgentWalletTransaction::DIRECTION_DEBIT,
                'amount' => 700000,
            ]
        );

        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/finance/agent-wallets/{$agent->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.balance',
                '0.00'
            );
    }

    public function test_draft_allocation_does_not_affect_wallet(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        $season = $this->season($admin);

        CashAllocation::create([
            'allocation_code' => 'CAL-000001',
            'coffee_season_id' => $season->id,
            'agent_id' => $agent->id,
            'amount' => 100000,
            'currency' => 'RWF',
            'payment_method' => 'cash',
            'allocation_date' => now()->toDateString(),
            'status' => CashAllocation::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseCount(
            'agent_wallet_transactions',
            0
        );
    }

    private function credit(
        Agent $agent,
        CoffeeSeason $season,
        float $amount,
        User $user
    ): AgentWalletTransaction {
        return AgentWalletTransaction::create([
            'agent_id' => $agent->id,
            'coffee_season_id' => $season->id,
            'type' => AgentWalletTransaction::TYPE_CASH_ALLOCATION,
            'direction' => AgentWalletTransaction::DIRECTION_CREDIT,
            'amount' => $amount,
            'currency' => 'RWF',
            'source_type' => 'test',
            'source_id' => $agent->id,
            'description' => 'Test wallet credit',
            'created_by' => $user->id,
        ]);
    }

    private function agent(): Agent
    {
        $user = User::factory()->create([
            'role' => User::ROLE_AGENT,
            'status' => 'active',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        return Agent::create([
            'agent_code' => sprintf(
                'AGT-%06d',
                $user->id
            ),
            'user_id' => $user->id,
            'status' => Agent::STATUS_ACTIVE,
            'created_by' => $user->id,
        ])->load('user');
    }

    private function season(User $admin): CoffeeSeason
    {
        return CoffeeSeason::create([
            'name' => 'Coffee Season 2026',
            'code' => 'CS-2026-001',
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

    private function admin(): User
    {
        return User::factory()
            ->admin()
            ->create([
                'must_change_password' => false,
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

        foreach ($roles as [$name, $displayName]) {
            DB::table('roles')->insert([
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
