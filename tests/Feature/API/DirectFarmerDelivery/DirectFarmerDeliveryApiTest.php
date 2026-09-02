<?php

namespace Tests\Feature\API\DirectFarmerDelivery;

use App\Models\CoffeePrice;
use App\Models\CoffeeSeason;
use App\Models\DirectFarmerDelivery;
use App\Models\Farmer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DirectFarmerDeliveryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_admin_can_create_draft_delivery_with_generated_code_and_calculated_amount(): void
    {
        $admin = $this->admin();
        $officer = $this->balanceOfficer();
        $farmer = $this->farmer($admin);

        $season = $this->season($admin);
        $price = $this->price($season, $admin, 1000);

        Sanctum::actingAs($admin);

        $response = $this->postJson(
            '/api/direct-farmer-deliveries',
            [
                'farmer_id' => $farmer->id,
                'balance_officer_id' => $officer->id,
                'coffee_type' => CoffeePrice::TYPE_PARCHMENT,
                'quantity_kg' => 120,
                'delivery_date' => now()->toDateString(),

                // These values must be ignored.
                'price_per_kg' => 1,
                'total_amount' => 1,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.delivery_code',
                'DFD-000001'
            )
            ->assertJsonPath(
                'data.status',
                DirectFarmerDelivery::STATUS_DRAFT
            )
            ->assertJsonPath(
                'data.payment_status',
                DirectFarmerDelivery::PAYMENT_UNPAID
            )
            ->assertJsonPath(
                'data.price_per_kg',
                '1000.00'
            )
            ->assertJsonPath(
                'data.total_amount',
                '120000.00'
            )
            ->assertJsonPath(
                'data.coffee_price_id',
                $price->id
            );
    }

    public function test_backend_generates_sequential_delivery_codes(): void
    {
        $admin = $this->admin();
        $officer = $this->balanceOfficer();
        $farmer = $this->farmer($admin);
        $season = $this->season($admin);

        $this->price($season, $admin);

        Sanctum::actingAs($admin);

        $payload = [
            'farmer_id' => $farmer->id,
            'balance_officer_id' => $officer->id,
            'coffee_type' => CoffeePrice::TYPE_PARCHMENT,
            'quantity_kg' => 50,
            'delivery_date' => now()->toDateString(),
        ];

        $this->postJson(
            '/api/direct-farmer-deliveries',
            $payload
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.delivery_code',
                'DFD-000001'
            );

        $this->postJson(
            '/api/direct-farmer-deliveries',
            $payload
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.delivery_code',
                'DFD-000002'
            );
    }

    public function test_balance_officer_can_record_own_delivery(): void
    {
        $admin = $this->admin();
        $officer = $this->balanceOfficer();
        $farmer = $this->farmer($admin);
        $season = $this->season($admin);

        $this->price($season, $admin);

        Sanctum::actingAs($officer);

        $this->postJson(
            '/api/direct-farmer-deliveries',
            [
                'farmer_id' => $farmer->id,
                'coffee_type' => CoffeePrice::TYPE_PARCHMENT,
                'quantity_kg' => 40,
                'delivery_date' => now()->toDateString(),
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.balance_officer_id',
                $officer->id
            );
    }

    public function test_accountant_can_record_delivery(): void
    {
        $admin = $this->admin();
        $accountant = $this->accountant();
        $officer = $this->balanceOfficer();
        $farmer = $this->farmer($admin);
        $season = $this->season($admin);

        $this->price($season, $admin);

        Sanctum::actingAs($accountant);

        $this->postJson(
            '/api/direct-farmer-deliveries',
            $this->payload(
                $farmer,
                $officer
            )
        )->assertCreated();
    }

    public function test_inactive_farmer_is_rejected(): void
    {
        $admin = $this->admin();
        $officer = $this->balanceOfficer();
        $farmer = $this->farmer($admin);

        $farmer->update([
            'status' => Farmer::STATUS_INACTIVE,
        ]);

        $season = $this->season($admin);
        $this->price($season, $admin);

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/direct-farmer-deliveries',
            $this->payload(
                $farmer,
                $officer
            )
        )->assertUnprocessable();
    }

    public function test_invalid_balance_officer_is_rejected(): void
    {
        $admin = $this->admin();
        $farmer = $this->farmer($admin);
        $season = $this->season($admin);

        $this->price($season, $admin);

        $driver = $this->user('driver');

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/direct-farmer-deliveries',
            $this->payload(
                $farmer,
                $driver
            )
        )->assertUnprocessable();
    }

    public function test_delivery_is_rejected_when_no_valid_active_price_exists(): void
    {
        $admin = $this->admin();
        $officer = $this->balanceOfficer();
        $farmer = $this->farmer($admin);
        $season = $this->season($admin);

        $this->price(
            $season,
            $admin,
            1000,
            [
                'effective_to' => now()
                    ->subDay()
                    ->toDateString(),
            ]
        );

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/direct-farmer-deliveries',
            $this->payload(
                $farmer,
                $officer
            )
        )->assertUnprocessable();
    }

    public function test_admin_can_update_draft_delivery_and_amount_is_recalculated(): void
    {
        $admin = $this->admin();

        [
            $delivery,
            $farmer,
            $officer,
        ] = $this->deliveryContext($admin);

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/direct-farmer-deliveries/{$delivery->id}",
            [
                'farmer_id' => $farmer->id,
                'balance_officer_id' => $officer->id,
                'coffee_type' => CoffeePrice::TYPE_PARCHMENT,
                'quantity_kg' => 200,
                'delivery_date' => now()->toDateString(),
                'purpose' => 'Updated delivery',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.quantity_kg',
                '200.00'
            )
            ->assertJsonPath(
                'data.total_amount',
                '200000.00'
            );
    }

    public function test_confirmed_delivery_cannot_be_edited(): void
    {
        $admin = $this->admin();

        [
            $delivery,
            $farmer,
            $officer,
        ] = $this->deliveryContext($admin);

        $delivery->update([
            'status' => DirectFarmerDelivery::STATUS_CONFIRMED,
            'confirmed_by' => $admin->id,
            'confirmed_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/direct-farmer-deliveries/{$delivery->id}",
            [
                'farmer_id' => $farmer->id,
                'balance_officer_id' => $officer->id,
                'coffee_type' => CoffeePrice::TYPE_PARCHMENT,
                'quantity_kg' => 300,
                'delivery_date' => now()->toDateString(),
            ]
        )->assertUnprocessable();
    }

    public function test_admin_can_confirm_draft_delivery(): void
    {
        $admin = $this->admin();

        [$delivery] =
            $this->deliveryContext($admin);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/confirm"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                DirectFarmerDelivery::STATUS_CONFIRMED
            );

        $this->assertDatabaseHas(
            'direct_farmer_deliveries',
            [
                'id' => $delivery->id,
                'status' => DirectFarmerDelivery::STATUS_CONFIRMED,
                'confirmed_by' => $admin->id,
            ]
        );
    }

    public function test_delivery_cannot_be_paid_before_confirmation(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        [$delivery] =
            $this->deliveryContext($admin);

        Sanctum::actingAs($admin);

        $this->uploadProof($delivery);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/pay",
            [
                'payment_method' =>
                    DirectFarmerDelivery::PAYMENT_CASH,
            ]
        )->assertUnprocessable();
    }

    public function test_payment_proof_can_be_uploaded(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        [$delivery] =
            $this->deliveryContext($admin);

        Sanctum::actingAs($admin);

        $response = $this->post(
            "/api/direct-farmer-deliveries/{$delivery->id}/payment-proof",
            [
                'payment_proof' =>
                    UploadedFile::fake()
                        ->image('farmer-payment.jpg'),
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response->assertOk();

        $delivery->refresh();

        $this->assertNotNull(
            $delivery->payment_proof_path
        );

        $this->assertSame(
            'farmer-payment.jpg',
            $delivery->payment_proof_original_name
        );

        Storage::disk('public')->assertExists(
            $delivery->payment_proof_path
        );
    }

    public function test_confirmed_delivery_cannot_be_paid_without_payment_proof(): void
    {
        $admin = $this->admin();

        [$delivery] =
            $this->deliveryContext($admin);

        $delivery->update([
            'status' => DirectFarmerDelivery::STATUS_CONFIRMED,
            'confirmed_by' => $admin->id,
            'confirmed_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/pay",
            [
                'payment_method' =>
                    DirectFarmerDelivery::PAYMENT_CASH,
            ]
        )->assertUnprocessable();
    }

    public function test_mobile_money_payment_requires_reference(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        [$delivery] =
            $this->deliveryContext($admin);

        $delivery->update([
            'status' => DirectFarmerDelivery::STATUS_CONFIRMED,
            'confirmed_by' => $admin->id,
            'confirmed_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->uploadProof($delivery);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/pay",
            [
                'payment_method' =>
                    DirectFarmerDelivery::PAYMENT_MOBILE_MONEY,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'payment_reference'
            );
    }

    public function test_confirmed_delivery_can_be_paid_after_proof_upload(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        [$delivery] =
            $this->deliveryContext($admin);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/confirm"
        )->assertOk();

        $this->uploadProof($delivery);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/pay",
            [
                'payment_method' =>
                    DirectFarmerDelivery::PAYMENT_MOBILE_MONEY,

                'payment_reference' =>
                    'MOMO-DFD-000001',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.payment_status',
                DirectFarmerDelivery::PAYMENT_PAID
            )
            ->assertJsonPath(
                'data.payment_method',
                DirectFarmerDelivery::PAYMENT_MOBILE_MONEY
            )
            ->assertJsonPath(
                'data.payment_reference',
                'MOMO-DFD-000001'
            );

        $this->assertDatabaseHas(
            'direct_farmer_deliveries',
            [
                'id' => $delivery->id,
                'payment_status' =>
                    DirectFarmerDelivery::PAYMENT_PAID,
                'paid_by' => $admin->id,
            ]
        );
    }

    public function test_delivery_cannot_be_paid_twice(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        [$delivery] =
            $this->deliveryContext($admin);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/confirm"
        )->assertOk();

        $this->uploadProof($delivery);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/pay",
            [
                'payment_method' =>
                    DirectFarmerDelivery::PAYMENT_CASH,
            ]
        )->assertOk();

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/pay",
            [
                'payment_method' =>
                    DirectFarmerDelivery::PAYMENT_CASH,
            ]
        )->assertUnprocessable();
    }

    public function test_payment_proof_cannot_be_replaced_after_payment(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        [$delivery] =
            $this->deliveryContext($admin);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/confirm"
        )->assertOk();

        $this->uploadProof($delivery);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/pay",
            [
                'payment_method' =>
                    DirectFarmerDelivery::PAYMENT_CASH,
            ]
        )->assertOk();

        $this->post(
            "/api/direct-farmer-deliveries/{$delivery->id}/payment-proof",
            [
                'payment_proof' =>
                    UploadedFile::fake()
                        ->image('replacement.jpg'),
            ],
            [
                'Accept' => 'application/json',
            ]
        )->assertUnprocessable();
    }

    public function test_unpaid_delivery_can_be_cancelled(): void
    {
        $admin = $this->admin();

        [$delivery] =
            $this->deliveryContext($admin);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/confirm"
        )->assertOk();

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/cancel",
            [
                'cancellation_reason' =>
                    'Wrong recorded weight',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                DirectFarmerDelivery::STATUS_CANCELLED
            );
    }

    public function test_paid_delivery_cannot_be_cancelled(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        [$delivery] =
            $this->deliveryContext($admin);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/confirm"
        )->assertOk();

        $this->uploadProof($delivery);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/pay",
            [
                'payment_method' =>
                    DirectFarmerDelivery::PAYMENT_CASH,
            ]
        )->assertOk();

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/cancel",
            [
                'cancellation_reason' =>
                    'Trying to cancel paid delivery',
            ]
        )->assertUnprocessable();
    }

    public function test_admin_accountant_and_balance_officer_can_access_deliveries(): void
    {
        foreach ([
            $this->admin(),
            $this->accountant(),
            $this->balanceOfficer(),
        ] as $user) {
            Sanctum::actingAs($user);

            $this->getJson(
                '/api/direct-farmer-deliveries'
            )->assertOk();
        }
    }

    public function test_other_operational_roles_cannot_access_deliveries(): void
    {
        foreach ([
            'agent',
            'driver',
            'store',
        ] as $role) {
            $user = $this->user($role);

            Sanctum::actingAs($user);

            $this->getJson(
                '/api/direct-farmer-deliveries'
            )->assertForbidden();
        }
    }

    public function test_unauthenticated_user_cannot_access_direct_farmer_deliveries(): void
    {
        $this->getJson(
            '/api/direct-farmer-deliveries'
        )->assertUnauthorized();
    }

    public function test_summary_returns_confirmed_and_paid_amounts(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        [$delivery] =
            $this->deliveryContext(
                $admin,
                100
            );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/confirm"
        )->assertOk();

        $this->uploadProof($delivery);

        $this->patchJson(
            "/api/direct-farmer-deliveries/{$delivery->id}/pay",
            [
                'payment_method' =>
                    DirectFarmerDelivery::PAYMENT_CASH,
            ]
        )->assertOk();

        $this->getJson(
            '/api/direct-farmer-deliveries/summary'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.confirmed_records',
                1
            )
            ->assertJsonPath(
                'data.confirmed_quantity_kg',
                '100.00'
            )
            ->assertJsonPath(
                'data.confirmed_amount',
                '100000.00'
            )
            ->assertJsonPath(
                'data.paid_amount',
                '100000.00'
            );
    }

    private function deliveryContext(
        User $admin,
        float $quantity = 120
    ): array {
        $officer = $this->balanceOfficer();
        $farmer = $this->farmer($admin);

        $season = $this->season($admin);

        $price = $this->price(
            $season,
            $admin,
            1000
        );

        $delivery = DirectFarmerDelivery::create([
            'coffee_season_id' => $season->id,
            'coffee_price_id' => $price->id,
            'farmer_id' => $farmer->id,
            'balance_officer_id' => $officer->id,

            'coffee_type' =>
                CoffeePrice::TYPE_PARCHMENT,

            'quantity_kg' => $quantity,
            'price_per_kg' => 1000,
            'total_amount' => $quantity * 1000,

            'currency' => 'RWF',

            'delivery_date' =>
                now()->toDateString(),

            'status' =>
                DirectFarmerDelivery::STATUS_DRAFT,

            'payment_status' =>
                DirectFarmerDelivery::PAYMENT_UNPAID,

            'created_by' => $admin->id,
        ]);

        return [
            $delivery,
            $farmer,
            $officer,
        ];
    }

    private function payload(
        Farmer $farmer,
        User $officer
    ): array {
        return [
            'farmer_id' => $farmer->id,

            'balance_officer_id' =>
                $officer->id,

            'coffee_type' =>
                CoffeePrice::TYPE_PARCHMENT,

            'quantity_kg' => 120,

            'delivery_date' =>
                now()->toDateString(),

            'purpose' =>
                'Direct delivery to washing station',
        ];
    }

    private function uploadProof(
        DirectFarmerDelivery $delivery
    ): void {
        $this->post(
            "/api/direct-farmer-deliveries/{$delivery->id}/payment-proof",
            [
                'payment_proof' =>
                    UploadedFile::fake()
                        ->image('payment-proof.jpg'),
            ],
            [
                'Accept' => 'application/json',
            ]
        )->assertOk();

        $delivery->refresh();
    }

    private function season(
        User $admin
    ): CoffeeSeason {
        return CoffeeSeason::create([
            'name' => 'Coffee Season 2026',
            'code' => 'CS-2026-001',

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

            'created_by' => $admin->id,
            'activated_by' => $admin->id,
            'activated_at' => now(),
        ]);
    }

    private function price(
        CoffeeSeason $season,
        User $admin,
        float $amount = 1000,
        array $overrides = []
    ): CoffeePrice {
        return CoffeePrice::create(
            array_merge(
                [
                    'coffee_season_id' =>
                        $season->id,

                    'code' =>
                        'PRICE-2026-001',

                    'coffee_type' =>
                        CoffeePrice::TYPE_PARCHMENT,

                    'price_per_kg' =>
                        $amount,

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
                ],
                $overrides
            )
        );
    }

    private function farmer(
        User $admin
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
                $this->villageId(),

            'preferred_payment_method' =>
                Farmer::PAYMENT_CASH,

            'status' =>
                Farmer::STATUS_ACTIVE,

            'created_by' =>
                $admin->id,
        ]);
    }

    private function villageId(): int
    {
        $provinceId = DB::table('provinces')
            ->insertGetId([
                'name' => 'Test Province',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $districtId = DB::table('districts')
            ->insertGetId([
                'province_id' => $provinceId,
                'name' => 'Test District',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $sectorId = DB::table('sectors')
            ->insertGetId([
                'district_id' => $districtId,
                'name' => 'Test Sector',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $cellId = DB::table('cells')
            ->insertGetId([
                'sector_id' => $sectorId,
                'name' => 'Test Cell',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return DB::table('villages')
            ->insertGetId([
                'cell_id' => $cellId,
                'name' => 'Test Village',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function admin(): User
    {
        return $this->user('admin');
    }

    private function accountant(): User
    {
        return $this->user('accountant');
    }

    private function balanceOfficer(): User
    {
        return $this->user('balance');
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
