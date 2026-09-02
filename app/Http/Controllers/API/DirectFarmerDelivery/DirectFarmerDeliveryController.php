<?php

namespace App\Http\Controllers\API\DirectFarmerDelivery;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\DirectFarmerDelivery\CancelDirectFarmerDeliveryRequest;
use App\Http\Requests\API\DirectFarmerDelivery\PayDirectFarmerDeliveryRequest;
use App\Http\Requests\API\DirectFarmerDelivery\StoreDirectFarmerDeliveryRequest;
use App\Http\Requests\API\DirectFarmerDelivery\UpdateDirectFarmerDeliveryRequest;
use App\Http\Requests\API\DirectFarmerDelivery\UploadDirectFarmerPaymentProofRequest;
use App\Models\CoffeePrice;
use App\Models\CoffeeSeason;
use App\Models\DirectFarmerDelivery;
use App\Models\Farmer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DirectFarmerDeliveryController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->allowed($request->user())) {
            return $this->sendError(
                'You are not allowed to view direct farmer deliveries.',
                [],
                403
            );
        }

        $query = DirectFarmerDelivery::query()
            ->with($this->relations())
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where(
                    'delivery_code',
                    'like',
                    "%{$search}%"
                )
                    ->orWhereHas(
                        'farmer',
                        fn ($farmer) =>
                            $farmer
                                ->where(
                                    'full_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'farmer_code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%"
                                )
                    );
            });
        }

        foreach ([
            'status',
            'payment_status',
            'coffee_type',
            'farmer_id',
            'balance_officer_id',
        ] as $field) {
            if ($request->filled($field)) {
                $query->where(
                    $field,
                    $request->input($field)
                );
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'delivery_date',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'delivery_date',
                '<=',
                $request->date_to
            );
        }

        $deliveries = $query->paginate(
            min(
                max(
                    (int) $request->get('per_page', 20),
                    1
                ),
                100
            )
        );

        return $this->sendResponse([
            'items' => $deliveries->items(),

            'pagination' => [
                'current_page' =>
                    $deliveries->currentPage(),

                'last_page' =>
                    $deliveries->lastPage(),

                'per_page' =>
                    $deliveries->perPage(),

                'total' =>
                    $deliveries->total(),
            ],
        ], 'Direct farmer deliveries retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        if (!$this->allowed($request->user())) {
            return $this->sendError(
                'You are not allowed to view this summary.',
                [],
                403
            );
        }

        $query = DirectFarmerDelivery::query();

        $confirmed = (clone $query)
            ->where(
                'status',
                DirectFarmerDelivery::STATUS_CONFIRMED
            );

        $paid = (clone $query)
            ->where(
                'payment_status',
                DirectFarmerDelivery::PAYMENT_PAID
            );

        return $this->sendResponse([
            'total_records' =>
                (clone $query)->count(),

            'draft_records' =>
                (clone $query)
                    ->where(
                        'status',
                        DirectFarmerDelivery::STATUS_DRAFT
                    )
                    ->count(),

            'confirmed_records' =>
                (clone $confirmed)->count(),

            'cancelled_records' =>
                (clone $query)
                    ->where(
                        'status',
                        DirectFarmerDelivery::STATUS_CANCELLED
                    )
                    ->count(),

            'confirmed_quantity_kg' =>
                $this->money(
                    (clone $confirmed)
                        ->sum('quantity_kg')
                ),

            'confirmed_amount' =>
                $this->money(
                    (clone $confirmed)
                        ->sum('total_amount')
                ),

            'paid_amount' =>
                $this->money(
                    (clone $paid)
                        ->sum('total_amount')
                ),

            'currency' => 'RWF',
        ], 'Direct farmer delivery summary retrieved successfully.');
    }

    public function balanceOfficers(
        Request $request
    ): JsonResponse {
        if (!$this->allowed($request->user())) {
            return $this->sendError(
                'You are not allowed to view balance officers.',
                [],
                403
            );
        }

        $officers = User::query()
            ->where('role', 'balance')
            ->where('status', 'active')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'phone',
            ]);

        return $this->sendResponse([
            'items' => $officers,
        ], 'Balance officers retrieved successfully.');
    }

    public function store(
        StoreDirectFarmerDeliveryRequest $request
    ): JsonResponse {
        $farmer = Farmer::find(
            $request->integer('farmer_id')
        );

        if (
            !$farmer ||
            $farmer->status !== Farmer::STATUS_ACTIVE
        ) {
            return $this->sendError(
                'Delivery can only be recorded for an active farmer.',
                [],
                422
            );
        }

        $officer = $this->resolveBalanceOfficer(
            $request
        );

        if (!$officer) {
            return $this->sendError(
                'A valid active Balance Officer is required.',
                [],
                422
            );
        }

        $price = $this->findPrice(
            $request->coffee_type,
            $request->delivery_date
        );

        if (!$price) {
            return $this->sendError(
                'No active coffee price was found for this coffee type and date.',
                [],
                422
            );
        }

        $quantity =
            (float) $request->quantity_kg;

        $total = round(
            $quantity *
            (float) $price->price_per_kg,
            2
        );

        $delivery = DirectFarmerDelivery::create([
            'coffee_season_id' =>
                $price->coffee_season_id,

            'coffee_price_id' =>
                $price->id,

            'farmer_id' =>
                $farmer->id,

            'balance_officer_id' =>
                $officer->id,

            'coffee_type' =>
                $price->coffee_type,

            'quantity_kg' =>
                $quantity,

            'price_per_kg' =>
                $price->price_per_kg,

            'total_amount' =>
                $total,

            'currency' =>
                $price->currency ?: 'RWF',

            'delivery_date' =>
                $request->delivery_date,

            'status' =>
                DirectFarmerDelivery::STATUS_DRAFT,

            'payment_status' =>
                DirectFarmerDelivery::PAYMENT_UNPAID,

            'purpose' =>
                $request->purpose,

            'created_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $delivery
                ->fresh()
                ->load($this->relations()),
            'Direct farmer delivery created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        DirectFarmerDelivery $directFarmerDelivery
    ): JsonResponse {
        if (!$this->allowed($request->user())) {
            return $this->sendError(
                'You are not allowed to view this delivery.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $directFarmerDelivery->load(
                $this->relations()
            ),
            'Direct farmer delivery retrieved successfully.'
        );
    }

    public function update(
        UpdateDirectFarmerDeliveryRequest $request,
        DirectFarmerDelivery $directFarmerDelivery
    ): JsonResponse {
        if (
            $directFarmerDelivery->status !==
            DirectFarmerDelivery::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft deliveries can be edited.',
                [],
                422
            );
        }

        $farmer = Farmer::find(
            $request->integer('farmer_id')
        );

        if (
            !$farmer ||
            $farmer->status !== Farmer::STATUS_ACTIVE
        ) {
            return $this->sendError(
                'Delivery can only be recorded for an active farmer.',
                [],
                422
            );
        }

        $officer = $this->resolveBalanceOfficer(
            $request
        );

        if (!$officer) {
            return $this->sendError(
                'A valid active Balance Officer is required.',
                [],
                422
            );
        }

        $price = $this->findPrice(
            $request->coffee_type,
            $request->delivery_date
        );

        if (!$price) {
            return $this->sendError(
                'No active coffee price was found for this coffee type and date.',
                [],
                422
            );
        }

        $quantity =
            (float) $request->quantity_kg;

        $directFarmerDelivery->update([
            'coffee_season_id' =>
                $price->coffee_season_id,

            'coffee_price_id' =>
                $price->id,

            'farmer_id' =>
                $farmer->id,

            'balance_officer_id' =>
                $officer->id,

            'coffee_type' =>
                $price->coffee_type,

            'quantity_kg' =>
                $quantity,

            'price_per_kg' =>
                $price->price_per_kg,

            'total_amount' =>
                round(
                    $quantity *
                    (float) $price->price_per_kg,
                    2
                ),

            'currency' =>
                $price->currency ?: 'RWF',

            'delivery_date' =>
                $request->delivery_date,

            'purpose' =>
                $request->purpose,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $directFarmerDelivery
                ->fresh()
                ->load($this->relations()),
            'Direct farmer delivery updated successfully.'
        );
    }

    public function confirm(
        Request $request,
        DirectFarmerDelivery $directFarmerDelivery
    ): JsonResponse {
        if (!$this->allowed($request->user())) {
            return $this->sendError(
                'You are not allowed to confirm this delivery.',
                [],
                403
            );
        }

        if (
            $directFarmerDelivery->status !==
            DirectFarmerDelivery::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft deliveries can be confirmed.',
                [],
                422
            );
        }

        $directFarmerDelivery->update([
            'status' =>
                DirectFarmerDelivery::STATUS_CONFIRMED,

            'confirmed_by' =>
                $request->user()->id,

            'confirmed_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $directFarmerDelivery
                ->fresh()
                ->load($this->relations()),
            'Direct farmer delivery confirmed successfully.'
        );
    }

    public function uploadProof(
        UploadDirectFarmerPaymentProofRequest $request,
        DirectFarmerDelivery $directFarmerDelivery
    ): JsonResponse {
        if (
            $directFarmerDelivery->status ===
            DirectFarmerDelivery::STATUS_CANCELLED
        ) {
            return $this->sendError(
                'Payment proof cannot be uploaded to a cancelled delivery.',
                [],
                422
            );
        }

        if (
            $directFarmerDelivery->payment_status ===
            DirectFarmerDelivery::PAYMENT_PAID
        ) {
            return $this->sendError(
                'Payment proof cannot be replaced after payment is completed.',
                [],
                422
            );
        }

        $file = $request->file('payment_proof');

        if ($directFarmerDelivery->payment_proof_path) {
            Storage::disk('public')->delete(
                $directFarmerDelivery
                    ->payment_proof_path
            );
        }

        $path = $file->store(
            "direct-farmer-deliveries/{$directFarmerDelivery->id}",
            'public'
        );

        $directFarmerDelivery->update([
            'payment_proof_path' =>
                $path,

            'payment_proof_original_name' =>
                $file->getClientOriginalName(),

            'payment_proof_mime_type' =>
                $file->getMimeType(),

            'payment_proof_size' =>
                $file->getSize(),

            'payment_proof_uploaded_by' =>
                $request->user()->id,

            'payment_proof_uploaded_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $directFarmerDelivery
                ->fresh()
                ->load($this->relations()),
            'Payment proof uploaded successfully.'
        );
    }

    public function pay(
        PayDirectFarmerDeliveryRequest $request,
        DirectFarmerDelivery $directFarmerDelivery
    ): JsonResponse {
        if (
            $directFarmerDelivery->status !==
            DirectFarmerDelivery::STATUS_CONFIRMED
        ) {
            return $this->sendError(
                'The delivery must be confirmed before payment.',
                [],
                422
            );
        }

        if (
            $directFarmerDelivery->payment_status ===
            DirectFarmerDelivery::PAYMENT_PAID
        ) {
            return $this->sendError(
                'This farmer has already been paid.',
                [],
                422
            );
        }

        if (!$directFarmerDelivery->payment_proof_path) {
            return $this->sendError(
                'Payment proof is required before marking this delivery as paid.',
                [],
                422
            );
        }

        $directFarmerDelivery->update([
            'payment_status' =>
                DirectFarmerDelivery::PAYMENT_PAID,

            'payment_method' =>
                $request->payment_method,

            'payment_reference' =>
                $request->payment_reference,

            'paid_by' =>
                $request->user()->id,

            'paid_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $directFarmerDelivery
                ->fresh()
                ->load($this->relations()),
            'Farmer payment recorded successfully.'
        );
    }

    public function cancel(
        CancelDirectFarmerDeliveryRequest $request,
        DirectFarmerDelivery $directFarmerDelivery
    ): JsonResponse {
        if (
            $directFarmerDelivery->status ===
            DirectFarmerDelivery::STATUS_CANCELLED
        ) {
            return $this->sendError(
                'This delivery is already cancelled.',
                [],
                422
            );
        }

        if (
            $directFarmerDelivery->payment_status ===
            DirectFarmerDelivery::PAYMENT_PAID
        ) {
            return $this->sendError(
                'A paid farmer delivery cannot be cancelled directly.',
                [],
                422
            );
        }

        $directFarmerDelivery->update([
            'status' =>
                DirectFarmerDelivery::STATUS_CANCELLED,

            'cancelled_by' =>
                $request->user()->id,

            'cancelled_at' =>
                now(),

            'cancellation_reason' =>
                trim(
                    $request->cancellation_reason
                ),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $directFarmerDelivery
                ->fresh()
                ->load($this->relations()),
            'Direct farmer delivery cancelled successfully.'
        );
    }

    private function findPrice(
        string $coffeeType,
        string $deliveryDate
    ): ?CoffeePrice {
        return CoffeePrice::query()
            ->where(
                'coffee_type',
                $coffeeType
            )
            ->where(
                'status',
                CoffeePrice::STATUS_ACTIVE
            )
            ->whereHas(
                'season',
                fn ($query) =>
                    $query->where(
                        'status',
                        CoffeeSeason::STATUS_ACTIVE
                    )
            )
            ->whereDate(
                'effective_from',
                '<=',
                $deliveryDate
            )
            ->where(function ($query) use ($deliveryDate) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate(
                        'effective_to',
                        '>=',
                        $deliveryDate
                    );
            })
            ->latest('effective_from')
            ->first();
    }

    private function resolveBalanceOfficer(
        Request $request
    ): ?User {
        if ($request->user()->role === 'balance') {
            return $request->user();
        }

        if (!$request->filled('balance_officer_id')) {
            return null;
        }

        return User::query()
            ->whereKey(
                $request->integer(
                    'balance_officer_id'
                )
            )
            ->where('role', 'balance')
            ->where('status', 'active')
            ->first();
    }

    private function allowed(?User $user): bool
    {
        return $user &&
            in_array($user->role, [
                'admin',
                'accountant',
                'balance',
            ], true);
    }

    private function relations(): array
    {
        return [
            'season:id,code,name,status',
            'coffeePrice:id,code,coffee_type,price_per_kg,currency',
            'farmer:id,farmer_code,full_name,phone,preferred_payment_method',
            'balanceOfficer:id,name,email,phone',
            'creator:id,name',
            'updater:id,name',
            'confirmer:id,name',
            'payer:id,name',
            'canceller:id,name',
            'proofUploader:id,name',
        ];
    }

    private function money($value): string
    {
        return number_format(
            (float) $value,
            2,
            '.',
            ''
        );
    }
}
