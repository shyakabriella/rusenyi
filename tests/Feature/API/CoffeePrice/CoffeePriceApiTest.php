<?php

namespace Tests\Feature\API\CoffeePrice;

use App\Models\CoffeePrice;
use App\Models\CoffeeSeason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoffeePriceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_admin_can_create_draft_coffee_price_with_generated_code(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_ACTIVE
            );

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/admin/coffee-prices',
            [
                'coffee_season_id' =>
                    $season->id,

                'coffee_type' =>
                    CoffeePrice::TYPE_CHERRY,

                'price_per_kg' =>
                    1200,

                'effective_from' =>
                    '2026-01-01',

                'notes' =>
                    'Main cherry purchase price.',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                'PRICE-2026-001'
            )
            ->assertJsonPath(
                'data.status',
                CoffeePrice::STATUS_DRAFT
            )
            ->assertJsonPath(
                'data.price_per_kg',
                1200
            );

        $this->assertDatabaseHas(
            'coffee_prices',
            [
                'coffee_season_id' =>
                    $season->id,

                'code' =>
                    'PRICE-2026-001',

                'coffee_type' =>
                    CoffeePrice::TYPE_CHERRY,

                'status' =>
                    CoffeePrice::STATUS_DRAFT,
            ]
        );
    }

    public function test_backend_generates_unique_price_codes(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_ACTIVE
            );

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/admin/coffee-prices',
            [
                'coffee_season_id' =>
                    $season->id,

                'coffee_type' =>
                    CoffeePrice::TYPE_CHERRY,

                'price_per_kg' =>
                    1200,

                'effective_from' =>
                    '2026-01-01',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                'PRICE-2026-001'
            );

        $this->postJson(
            '/api/admin/coffee-prices',
            [
                'coffee_season_id' =>
                    $season->id,

                'coffee_type' =>
                    CoffeePrice::TYPE_CHERRY,

                'price_per_kg' =>
                    1300,

                'effective_from' =>
                    '2026-02-01',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                'PRICE-2026-002'
            );
    }

    public function test_price_cannot_be_created_for_closed_season(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_CLOSED
            );

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/admin/coffee-prices',
            [
                'coffee_season_id' =>
                    $season->id,

                'coffee_type' =>
                    CoffeePrice::TYPE_CHERRY,

                'price_per_kg' =>
                    1200,

                'effective_from' =>
                    '2026-01-01',
            ]
        )->assertUnprocessable();
    }

    public function test_price_effective_date_must_be_inside_season(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_ACTIVE
            );

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/admin/coffee-prices',
            [
                'coffee_season_id' =>
                    $season->id,

                'coffee_type' =>
                    CoffeePrice::TYPE_CHERRY,

                'price_per_kg' =>
                    1200,

                'effective_from' =>
                    '2025-12-01',
            ]
        )->assertUnprocessable();
    }

    public function test_admin_can_update_draft_price(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_ACTIVE
            );

        $price =
            $this->price(
                $admin,
                $season
            );

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/coffee-prices/{$price->id}",
            [
                'coffee_season_id' =>
                    $season->id,

                'coffee_type' =>
                    CoffeePrice::TYPE_CHERRY,

                'price_per_kg' =>
                    1500,

                'effective_from' =>
                    '2026-01-01',

                'notes' =>
                    'Updated price.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.price_per_kg',
                1500
            );
    }

    public function test_active_price_cannot_be_edited(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_ACTIVE
            );

        $price =
            $this->price(
                $admin,
                $season,
                CoffeePrice::STATUS_ACTIVE
            );

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/coffee-prices/{$price->id}",
            [
                'coffee_season_id' =>
                    $season->id,

                'coffee_type' =>
                    CoffeePrice::TYPE_CHERRY,

                'price_per_kg' =>
                    1600,

                'effective_from' =>
                    '2026-01-01',
            ]
        )->assertUnprocessable();
    }

    public function test_admin_can_activate_price_for_active_season(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_ACTIVE
            );

        $price =
            $this->price(
                $admin,
                $season
            );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/coffee-prices/{$price->id}/activate"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                CoffeePrice::STATUS_ACTIVE
            )
            ->assertJsonPath(
                'data.is_active',
                true
            );

        $this->assertDatabaseHas(
            'coffee_prices',
            [
                'id' =>
                    $price->id,

                'status' =>
                    CoffeePrice::STATUS_ACTIVE,

                'activated_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_price_cannot_be_activated_when_season_is_not_active(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_DRAFT
            );

        $price =
            $this->price(
                $admin,
                $season
            );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/coffee-prices/{$price->id}/activate"
        )->assertUnprocessable();

        $this->assertDatabaseHas(
            'coffee_prices',
            [
                'id' =>
                    $price->id,

                'status' =>
                    CoffeePrice::STATUS_DRAFT,
            ]
        );
    }

    public function test_only_one_active_price_per_coffee_type_per_season(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_ACTIVE
            );

        $this->price(
            $admin,
            $season,
            CoffeePrice::STATUS_ACTIVE,
            'PRICE-2026-001',
            1200,
            '2026-01-01'
        );

        $second =
            $this->price(
                $admin,
                $season,
                CoffeePrice::STATUS_DRAFT,
                'PRICE-2026-002',
                1300,
                '2026-02-01'
            );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/coffee-prices/{$second->id}/activate"
        )->assertUnprocessable();

        $this->assertDatabaseHas(
            'coffee_prices',
            [
                'id' =>
                    $second->id,

                'status' =>
                    CoffeePrice::STATUS_DRAFT,
            ]
        );
    }

    public function test_different_coffee_types_can_have_active_prices_in_same_season(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_ACTIVE
            );

        $this->price(
            $admin,
            $season,
            CoffeePrice::STATUS_ACTIVE,
            'PRICE-2026-001',
            1200,
            '2026-01-01',
            CoffeePrice::TYPE_CHERRY
        );

        $parchment =
            $this->price(
                $admin,
                $season,
                CoffeePrice::STATUS_DRAFT,
                'PRICE-2026-002',
                2500,
                '2026-01-01',
                CoffeePrice::TYPE_PARCHMENT
            );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/coffee-prices/{$parchment->id}/activate"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                CoffeePrice::STATUS_ACTIVE
            );
    }

    public function test_admin_can_deactivate_active_price(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_ACTIVE
            );

        $price =
            $this->price(
                $admin,
                $season,
                CoffeePrice::STATUS_ACTIVE
            );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/coffee-prices/{$price->id}/deactivate"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                CoffeePrice::STATUS_INACTIVE
            )
            ->assertJsonPath(
                'data.is_active',
                false
            );

        $this->assertDatabaseHas(
            'coffee_prices',
            [
                'id' =>
                    $price->id,

                'status' =>
                    CoffeePrice::STATUS_INACTIVE,

                'deactivated_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_authenticated_user_can_read_current_active_price(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_ACTIVE
            );

        $price =
            $this->price(
                $admin,
                $season,
                CoffeePrice::STATUS_ACTIVE
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
            '/api/coffee-prices/active?coffee_type=cherry'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.season.id',
                $season->id
            )
            ->assertJsonPath(
                'data.prices.0.id',
                $price->id
            )
            ->assertJsonPath(
                'data.prices.0.price_per_kg',
                1200
            );
    }

    public function test_active_price_endpoint_returns_empty_when_no_active_season_exists(): void
    {
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
            '/api/coffee-prices/active'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.season',
                null
            )
            ->assertJsonCount(
                0,
                'data.prices'
            );
    }

    public function test_non_admin_cannot_manage_coffee_prices(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_ACTIVE
            );

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
            '/api/admin/coffee-prices',
            [
                'coffee_season_id' =>
                    $season->id,

                'coffee_type' =>
                    CoffeePrice::TYPE_CHERRY,

                'price_per_kg' =>
                    1200,

                'effective_from' =>
                    '2026-01-01',
            ]
        )->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_read_active_prices(): void
    {
        $this->getJson(
            '/api/coffee-prices/active'
        )->assertUnauthorized();
    }

    public function test_admin_can_search_filter_and_paginate_prices(): void
    {
        $admin =
            $this->admin();

        $season =
            $this->season(
                $admin,
                CoffeeSeason::STATUS_ACTIVE
            );

        $this->price(
            $admin,
            $season,
            CoffeePrice::STATUS_DRAFT,
            'PRICE-2026-001'
        );

        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/admin/coffee-prices?coffee_season_id={$season->id}&coffee_type=cherry&status=draft&search=PRICE-2026&per_page=10"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.items.0.code',
                'PRICE-2026-001'
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

    private function season(
        User $user,
        string $status
    ): CoffeeSeason {
        return CoffeeSeason::create([
            'name' =>
                '2026 Main Coffee Season',

            'code' =>
                'CS-2026-001',

            'start_date' =>
                '2026-01-01',

            'end_date' =>
                '2026-12-31',

            'status' =>
                $status,

            'created_by' =>
                $user->id,

            'activated_by' =>
                $status ===
                CoffeeSeason::STATUS_ACTIVE
                    ? $user->id
                    : null,

            'activated_at' =>
                $status ===
                CoffeeSeason::STATUS_ACTIVE
                    ? now()
                    : null,

            'closed_by' =>
                $status ===
                CoffeeSeason::STATUS_CLOSED
                    ? $user->id
                    : null,

            'closed_at' =>
                $status ===
                CoffeeSeason::STATUS_CLOSED
                    ? now()
                    : null,
        ]);
    }

    private function price(
        User $user,
        CoffeeSeason $season,
        string $status =
            CoffeePrice::STATUS_DRAFT,
        string $code =
            'PRICE-2026-001',
        float $amount = 1200,
        string $effectiveFrom =
            '2026-01-01',
        string $coffeeType =
            CoffeePrice::TYPE_CHERRY
    ): CoffeePrice {
        return CoffeePrice::create([
            'coffee_season_id' =>
                $season->id,

            'code' =>
                $code,

            'coffee_type' =>
                $coffeeType,

            'price_per_kg' =>
                $amount,

            'currency' =>
                'RWF',

            'effective_from' =>
                $effectiveFrom,

            'status' =>
                $status,

            'created_by' =>
                $user->id,

            'activated_by' =>
                $status ===
                CoffeePrice::STATUS_ACTIVE
                    ? $user->id
                    : null,

            'activated_at' =>
                $status ===
                CoffeePrice::STATUS_ACTIVE
                    ? now()
                    : null,

            'deactivated_by' =>
                $status ===
                CoffeePrice::STATUS_INACTIVE
                    ? $user->id
                    : null,

            'deactivated_at' =>
                $status ===
                CoffeePrice::STATUS_INACTIVE
                    ? now()
                    : null,
        ]);
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
