<?php

namespace Tests\Feature\API\Finance;

use App\Models\Agent;
use App\Models\CashAllocation;
use App\Models\CoffeeSeason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashAllocationPaymentEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_new_allocation_defaults_to_cash_payment_method(): void
    {
        $admin = $this->admin();

        Sanctum::actingAs($admin);

        $response =
            $this->postJson(
                '/api/finance/cash-allocations',
                [
                    'coffee_season_id' =>
                        $this
                            ->season($admin)
                            ->id,

                    'agent_id' =>
                        $this
                            ->agent($admin)
                            ->id,

                    'amount' =>
                        100000,

                    'allocation_date' =>
                        now()
                            ->toDateString(),
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.payment_method',
                'cash'
            );
    }

    public function test_mobile_money_requires_payment_reference(): void
    {
        $admin = $this->admin();

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/finance/cash-allocations',
            [
                'coffee_season_id' =>
                    $this
                        ->season($admin)
                        ->id,

                'agent_id' =>
                    $this
                        ->agent($admin)
                        ->id,

                'amount' =>
                    100000,

                'payment_method' =>
                    CashAllocation::PAYMENT_METHOD_MOBILE_MONEY,

                'allocation_date' =>
                    now()
                        ->toDateString(),
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'reference'
            );
    }

    public function test_admin_can_upload_payment_proof_to_draft_allocation(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        Sanctum::actingAs($admin);

        $allocation =
            $this->allocation(
                $admin,
                $this->season($admin),
                $this->agent($admin)
            );

        $file =
            UploadedFile::fake()
                ->image(
                    'cash-voucher.jpg'
                );

        $response =
            $this->post(
                "/api/finance/cash-allocations/{$allocation->id}/proof",
                [
                    'payment_proof' =>
                        $file,
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.payment_proof.exists',
                true
            );

        $allocation->refresh();

        $this->assertNotNull(
            $allocation
                ->payment_proof_path
        );

        Storage::disk('public')
            ->assertExists(
                $allocation
                    ->payment_proof_path
            );
    }

    public function test_new_allocation_cannot_be_approved_without_payment_proof(): void
    {
        $admin = $this->admin();

        Sanctum::actingAs($admin);

        $allocation =
            $this->allocation(
                $admin,
                $this->season($admin),
                $this->agent($admin)
            );

        $this->patchJson(
            "/api/finance/cash-allocations/{$allocation->id}/approve"
        )
            ->assertUnprocessable();
    }

    public function test_allocation_can_be_approved_after_payment_proof_is_uploaded(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        Sanctum::actingAs($admin);

        $allocation =
            $this->allocation(
                $admin,
                $this->season($admin),
                $this->agent($admin)
            );

        $this->post(
            "/api/finance/cash-allocations/{$allocation->id}/proof",
            [
                'payment_proof' =>
                    UploadedFile::fake()
                        ->create(
                            'cash-voucher.pdf',
                            200,
                            'application/pdf'
                        ),
            ],
            [
                'Accept' =>
                    'application/json',
            ]
        )->assertOk();

        $this->patchJson(
            "/api/finance/cash-allocations/{$allocation->id}/approve"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'approved'
            );
    }

    public function test_payment_proof_cannot_be_replaced_after_approval(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        Sanctum::actingAs($admin);

        $allocation =
            $this->allocation(
                $admin,
                $this->season($admin),
                $this->agent($admin)
            );

        $this->post(
            "/api/finance/cash-allocations/{$allocation->id}/proof",
            [
                'payment_proof' =>
                    UploadedFile::fake()
                        ->image(
                            'proof.jpg'
                        ),
            ],
            [
                'Accept' =>
                    'application/json',
            ]
        )->assertOk();

        $this->patchJson(
            "/api/finance/cash-allocations/{$allocation->id}/approve"
        )->assertOk();

        $this->post(
            "/api/finance/cash-allocations/{$allocation->id}/proof",
            [
                'payment_proof' =>
                    UploadedFile::fake()
                        ->image(
                            'another-proof.jpg'
                        ),
            ],
            [
                'Accept' =>
                    'application/json',
            ]
        )->assertUnprocessable();
    }

    private function allocation(
        User $admin,
        CoffeeSeason $season,
        Agent $agent
    ): CashAllocation {
        return CashAllocation::create([
            'allocation_code' =>
                'CAL-000001',

            'coffee_season_id' =>
                $season->id,

            'agent_id' =>
                $agent->id,

            'amount' =>
                100000,

            'currency' =>
                'RWF',

            'payment_method' =>
                CashAllocation::PAYMENT_METHOD_CASH,

            'allocation_date' =>
                now()
                    ->toDateString(),

            'status' =>
                CashAllocation::STATUS_DRAFT,

            'created_by' =>
                $admin->id,
        ]);
    }

    private function season(
        User $admin
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

    private function agent(
        User $admin
    ): Agent {
        $user =
            User::factory()
                ->create([
                    'role' =>
                        User::ROLE_AGENT,

                    'status' =>
                        'active',

                    'must_change_password' =>
                        false,
                ]);

        return Agent::create([
            'agent_code' =>
                'AGT-000001',

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
