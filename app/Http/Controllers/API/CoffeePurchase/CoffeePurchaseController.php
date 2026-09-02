<?php

namespace App\Http\Controllers\API\CoffeePurchase;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\CoffeePurchase\CancelCoffeePurchaseRequest;
use App\Http\Requests\API\CoffeePurchase\StoreCoffeePurchaseRequest;
use App\Http\Requests\API\CoffeePurchase\UpdateCoffeePurchaseRequest;
use App\Models\Agent;
use App\Models\AgentCollection;
use App\Models\CoffeePrice;
use App\Models\CoffeePurchase;
use App\Models\CoffeeSeason;
use App\Models\Farmer;
use App\Models\User;
use App\Services\Finance\AgentWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoffeePurchaseController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->allowed($user)) {
            return $this->sendError(
                'You are not allowed to view coffee purchases.',
                [],
                403
            );
        }

        $query = CoffeePurchase::query()
            ->with($this->relations());

        if ($user->role === User::ROLE_AGENT) {
            $agent = $this->agentForUser($user);

            if (!$agent) {
                return $this->sendError(
                    'Agent profile was not found.',
                    [],
                    404
                );
            }

            $query->where('agent_id', $agent->id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('purchase_code', 'like', "%{$search}%")
                    ->orWhereHas('farmer', fn ($farmer) =>
                        $farmer
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                    )
                    ->orWhereHas('agent', fn ($agent) =>
                        $agent
                            ->where('agent_code', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($user) =>
                                $user->where('name', 'like', "%{$search}%")
                            )
                    );
            });
        }

        foreach ([
            'status',
            'coffee_type',
            'agent_id',
            'farmer_id',
        ] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'purchase_date',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'purchase_date',
                '<=',
                $request->date_to
            );
        }

        $purchases = $query
            ->latest('id')
            ->paginate(
                min(max((int) $request->get('per_page', 20), 1), 100)
            );

        return $this->sendResponse([
            'items' => $purchases->items(),
            'pagination' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'per_page' => $purchases->perPage(),
                'total' => $purchases->total(),
            ],
        ], 'Coffee purchases retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->allowed($user)) {
            return $this->sendError(
                'You are not allowed to view coffee purchase summary.',
                [],
                403
            );
        }

        $query = CoffeePurchase::query();

        if ($user->role === User::ROLE_AGENT) {
            $agent = $this->agentForUser($user);

            if (!$agent) {
                return $this->sendError(
                    'Agent profile was not found.',
                    [],
                    404
                );
            }

            $query->where('agent_id', $agent->id);
        }

        $approved = (clone $query)
            ->where('status', CoffeePurchase::STATUS_APPROVED);

        return $this->sendResponse([
            'total_records' => (clone $query)->count(),

            'draft_records' => (clone $query)
                ->where('status', CoffeePurchase::STATUS_DRAFT)
                ->count(),

            'approved_records' => (clone $approved)->count(),

            'cancelled_records' => (clone $query)
                ->where('status', CoffeePurchase::STATUS_CANCELLED)
                ->count(),

            'approved_quantity_kg' => number_format(
                (float) (clone $approved)->sum('quantity_kg'),
                2,
                '.',
                ''
            ),

            'approved_amount' => number_format(
                (float) (clone $approved)->sum('total_amount'),
                2,
                '.',
                ''
            ),

            'currency' => 'RWF',
        ], 'Coffee purchase summary retrieved successfully.');
    }

    public function store(
        StoreCoffeePurchaseRequest $request
    ): JsonResponse {
        $agent = $this->resolveAgent($request);

        if (!$agent) {
            return $this->sendError(
                'A valid active agent is required.',
                [],
                422
            );
        }

        $farmer = Farmer::find($request->integer('farmer_id'));

        if (!$farmer || $farmer->status !== Farmer::STATUS_ACTIVE) {
            return $this->sendError(
                'Coffee can only be purchased from an active farmer.',
                [],
                422
            );
        }

        $price = $this->findPrice(
            $request->coffee_type,
            $request->purchase_date
        );

        if (!$price) {
            return $this->sendError(
                'No active coffee price was found for this coffee type and date.',
                [],
                422
            );
        }

        $quantity = (float) $request->quantity_kg;
        $unitPrice = (float) $price->price_per_kg;
        $total = round($quantity * $unitPrice, 2);

        $purchase = CoffeePurchase::create([
            'coffee_season_id' => $price->coffee_season_id,
            'coffee_price_id' => $price->id,
            'agent_id' => $agent->id,
            'farmer_id' => $farmer->id,

            'collection_point_id' =>
                $request->integer('collection_point_id')
                ?: $farmer->collection_point_id,

            'coffee_type' => $price->coffee_type,
            'quantity_kg' => $quantity,
            'price_per_kg' => $price->price_per_kg,
            'total_amount' => $total,
            'currency' => $price->currency ?: 'RWF',
            'purchase_date' => $request->purchase_date,
            'purpose' => $request->purpose,
            'status' => CoffeePurchase::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);

        return $this->sendResponse(
            $purchase->fresh()->load($this->relations()),
            'Coffee purchase created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        CoffeePurchase $coffeePurchase
    ): JsonResponse {
        if (!$this->canAccess($request->user(), $coffeePurchase)) {
            return $this->sendError(
                'You are not allowed to view this coffee purchase.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $coffeePurchase->load($this->relations()),
            'Coffee purchase retrieved successfully.'
        );
    }

    public function update(
        UpdateCoffeePurchaseRequest $request,
        CoffeePurchase $coffeePurchase
    ): JsonResponse {
        if ($coffeePurchase->status !== CoffeePurchase::STATUS_DRAFT) {
            return $this->sendError(
                'Only draft coffee purchases can be edited.',
                [],
                422
            );
        }

        if (!$this->canAccess($request->user(), $coffeePurchase)) {
            return $this->sendError(
                'You are not allowed to edit this coffee purchase.',
                [],
                403
            );
        }

        $farmer = Farmer::find($request->integer('farmer_id'));

        if (!$farmer || $farmer->status !== Farmer::STATUS_ACTIVE) {
            return $this->sendError(
                'Coffee can only be purchased from an active farmer.',
                [],
                422
            );
        }

        $price = $this->findPrice(
            $request->coffee_type,
            $request->purchase_date
        );

        if (!$price) {
            return $this->sendError(
                'No active coffee price was found for this coffee type and date.',
                [],
                422
            );
        }

        $quantity = (float) $request->quantity_kg;
        $total = round(
            $quantity * (float) $price->price_per_kg,
            2
        );

        $coffeePurchase->update([
            'coffee_season_id' => $price->coffee_season_id,
            'coffee_price_id' => $price->id,
            'farmer_id' => $farmer->id,

            'collection_point_id' =>
                $request->integer('collection_point_id')
                ?: $farmer->collection_point_id,

            'coffee_type' => $price->coffee_type,
            'quantity_kg' => $quantity,
            'price_per_kg' => $price->price_per_kg,
            'total_amount' => $total,
            'currency' => $price->currency ?: 'RWF',
            'purchase_date' => $request->purchase_date,
            'purpose' => $request->purpose,
            'updated_by' => $request->user()->id,
        ]);

        return $this->sendResponse(
            $coffeePurchase->fresh()->load($this->relations()),
            'Coffee purchase updated successfully.'
        );
    }

    public function approve(
        Request $request,
        CoffeePurchase $coffeePurchase,
        AgentWalletService $wallet
    ): JsonResponse {
        if (!$this->financeUser($request->user())) {
            return $this->sendError(
                'Only Admin or Accountant can approve coffee purchases.',
                [],
                403
            );
        }

        if ($coffeePurchase->status !== CoffeePurchase::STATUS_DRAFT) {
            return $this->sendError(
                'Only draft coffee purchases can be approved.',
                [],
                422
            );
        }

        return DB::transaction(function () use (
            $request,
            $coffeePurchase,
            $wallet
        ) {
            Agent::query()
                ->whereKey($coffeePurchase->agent_id)
                ->lockForUpdate()
                ->first();

            $balance = $wallet->balance(
                $coffeePurchase->agent_id,
                $coffeePurchase->coffee_season_id
            );

            if ($balance < (float) $coffeePurchase->total_amount) {
                return $this->sendError(
                    'The agent does not have enough available wallet balance for this purchase.',
                    [
                        'available_balance' => number_format(
                            $balance,
                            2,
                            '.',
                            ''
                        ),
                        'required_amount' => $coffeePurchase->total_amount,
                    ],
                    422
                );
            }

            $coffeePurchase->update([
                'status' => CoffeePurchase::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'updated_by' => $request->user()->id,
            ]);

            return $this->sendResponse(
                $coffeePurchase->fresh()->load($this->relations()),
                'Coffee purchase approved successfully.'
            );
        });
    }

    public function cancel(
        CancelCoffeePurchaseRequest $request,
        CoffeePurchase $coffeePurchase
    ): JsonResponse {
        if ($coffeePurchase->status === CoffeePurchase::STATUS_CANCELLED) {
            return $this->sendError(
                'This coffee purchase is already cancelled.',
                [],
                422
            );
        }

        $user = $request->user();

        if ($user->role === User::ROLE_AGENT) {
            if (
                $coffeePurchase->status !== CoffeePurchase::STATUS_DRAFT ||
                !$this->canAccess($user, $coffeePurchase)
            ) {
                return $this->sendError(
                    'Agents can only cancel their own draft purchases.',
                    [],
                    403
                );
            }
        } elseif (!$this->financeUser($user)) {
            return $this->sendError(
                'You are not allowed to cancel this coffee purchase.',
                [],
                403
            );
        }

        if ($coffeePurchase->agent_collection_id) {
            $collection = AgentCollection::find(
                $coffeePurchase->agent_collection_id
            );

            if (
                $collection &&
                $collection->status ===
                    AgentCollection::STATUS_COMPLETED
            ) {
                return $this->sendError(
                    'This purchase belongs to a completed agent collection and cannot be cancelled directly.',
                    [],
                    422
                );
            }

            $coffeePurchase->update([
                'agent_collection_id' => null,
            ]);
        }

        $coffeePurchase->update([
            'status' => CoffeePurchase::STATUS_CANCELLED,
            'cancelled_by' => $user->id,
            'cancelled_at' => now(),
            'cancellation_reason' =>
                trim($request->cancellation_reason),
            'updated_by' => $user->id,
        ]);

        return $this->sendResponse(
            $coffeePurchase->fresh()->load($this->relations()),
            'Coffee purchase cancelled successfully.'
        );
    }

    private function findPrice(
        string $coffeeType,
        string $purchaseDate
    ): ?CoffeePrice {
        return CoffeePrice::query()
            ->where('coffee_type', $coffeeType)
            ->where('status', CoffeePrice::STATUS_ACTIVE)
            ->whereHas('season', fn ($query) =>
                $query->where(
                    'status',
                    CoffeeSeason::STATUS_ACTIVE
                )
            )
            ->whereDate('effective_from', '<=', $purchaseDate)
            ->where(function ($query) use ($purchaseDate) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $purchaseDate);
            })
            ->latest('effective_from')
            ->first();
    }

    private function resolveAgent(
        Request $request
    ): ?Agent {
        $user = $request->user();

        if ($user->role === User::ROLE_AGENT) {
            return $this->agentForUser($user);
        }

        if (!$request->filled('agent_id')) {
            return null;
        }

        return Agent::query()
            ->with('user')
            ->whereKey($request->integer('agent_id'))
            ->where('status', Agent::STATUS_ACTIVE)
            ->whereHas('user', fn ($query) =>
                $query->where('status', 'active')
            )
            ->first();
    }

    private function agentForUser(User $user): ?Agent
    {
        return Agent::query()
            ->where('user_id', $user->id)
            ->where('status', Agent::STATUS_ACTIVE)
            ->first();
    }

    private function canAccess(
        User $user,
        CoffeePurchase $purchase
    ): bool {
        if ($this->financeUser($user)) {
            return true;
        }

        if ($user->role !== User::ROLE_AGENT) {
            return false;
        }

        $agent = $this->agentForUser($user);

        return $agent &&
            $agent->id === $purchase->agent_id;
    }

    private function financeUser(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_ADMIN,
            User::ROLE_ACCOUNTANT,
        ], true);
    }

    private function allowed(User $user): bool
    {
        return $this->financeUser($user) ||
            $user->role === User::ROLE_AGENT;
    }

    private function relations(): array
    {
        return [
            'season:id,code,name,status',
            'coffeePrice:id,code,coffee_type,price_per_kg,currency',
            'agent.user:id,name,email,phone',
            'farmer:id,farmer_code,full_name,phone',
            'collectionPoint:id,name,code',
            'creator:id,name',
            'updater:id,name',
            'approver:id,name',
            'canceller:id,name',
        ];
    }
}
