<?php

namespace Tests\Feature\API\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $this->getJson('/api/dashboard/overview')
            ->assertUnauthorized();
    }

    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()
            ->admin()
            ->create([
                'must_change_password' => false,
            ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.viewer_role',
                User::ROLE_ADMIN
            )
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'viewer_role',
                    'generated_at',
                    'currency',
                    'cards' => [
                        'coffee_received_today' => [
                            'value',
                            'unit',
                            'change_percent',
                        ],
                        'coffee_purchased_today' => [
                            'value',
                            'unit',
                            'change_percent',
                        ],
                        'money_used_today' => [
                            'value',
                            'unit',
                            'change_percent',
                        ],
                        'available_cash' => [
                            'value',
                            'unit',
                            'change_percent',
                            'scope',
                        ],
                    ],
                    'operations',
                    'finance',
                    'weekly_received',
                    'source_breakdown',
                    'recent_activities',
                    'top_agents',
                ],
            ]);
    }

    public function test_accountant_can_access_dashboard(): void
    {
        $accountant = User::factory()
            ->accountant()
            ->create([
                'must_change_password' => false,
            ]);

        Sanctum::actingAs($accountant);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath(
                'data.viewer_role',
                User::ROLE_ACCOUNTANT
            );
    }

    public function test_agent_cannot_access_web_dashboard(): void
    {
        $agent = User::factory()->create([
            'role' => User::ROLE_AGENT,
            'must_change_password' => false,
        ]);

        Sanctum::actingAs($agent);

        $this->getJson('/api/dashboard/overview')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_empty_dashboard_returns_zero_values(): void
    {
        $admin = User::factory()
            ->admin()
            ->create([
                'must_change_password' => false,
            ]);

        Sanctum::actingAs($admin);

        $response =
            $this->getJson('/api/dashboard/overview')
                ->assertOk();

        $response
            ->assertJsonPath(
                'data.cards.coffee_received_today.value',
                0
            )
            ->assertJsonPath(
                'data.cards.coffee_purchased_today.value',
                0
            )
            ->assertJsonPath(
                'data.cards.money_used_today.value',
                0
            )
            ->assertJsonPath(
                'data.operations.store_stock_kg',
                0
            )
            ->assertJsonPath(
                'data.operations.pending_approvals',
                0
            );

        $this->assertCount(
            7,
            $response->json(
                'data.weekly_received'
            )
        );

        $this->assertCount(
            2,
            $response->json(
                'data.source_breakdown'
            )
        );
    }

    public function test_dashboard_counts_only_active_agent_users(): void
    {
        $admin = User::factory()
            ->admin()
            ->create([
                'must_change_password' => false,
            ]);

        User::factory()
            ->count(2)
            ->create([
                'role' => User::ROLE_AGENT,
                'status' => 'active',
                'is_active' => true,
                'must_change_password' => false,
            ]);

        User::factory()->create([
            'role' => User::ROLE_AGENT,
            'status' => 'inactive',
            'is_active' => false,
            'must_change_password' => false,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath(
                'data.operations.active_agents',
                2
            );
    }
}
