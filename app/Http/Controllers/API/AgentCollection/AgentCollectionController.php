<?php

namespace App\Http\Controllers\API\AgentCollection;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\AgentCollection\AddAgentCollectionPurchasesRequest;
use App\Http\Requests\API\AgentCollection\CancelAgentCollectionRequest;
use App\Http\Requests\API\AgentCollection\StoreAgentCollectionRequest;
use App\Http\Requests\API\AgentCollection\UpdateAgentCollectionRequest;
use App\Models\Agent;
use App\Models\AgentCollection;
use App\Models\CoffeePurchase;
use App\Models\CoffeeSeason;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgentCollectionController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view agent collections.',
                [],
                403
            );
        }

        $query = AgentCollection::query()
            ->with($this->listRelations());

        $this->restrictAgent($query, $user);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($query) use ($search) {
                $query
                    ->where(
                        'collection_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'agent',
                        fn ($agent) =>
                            $agent
                                ->where(
                                    'agent_code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhereHas(
                                    'user',
                                    fn ($user) =>
                                        $user->where(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        )
                                )
                    );
            });
        }

        foreach ([
            'status',
            'agent_id',
            'collection_point_id',
            'coffee_season_id',
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
                'collection_date',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'collection_date',
                '<=',
                $request->date_to
            );
        }

        $collections = $query
            ->latest('id')
            ->paginate(
                min(
                    max(
                        (int) $request->get(
                            'per_page',
                            20
                        ),
                        1
                    ),
                    100
                )
            );

        return $this->sendResponse([
            'items' => collect(
                $collections->items()
            )
                ->map(
                    fn ($collection) =>
                        $this->collectionData(
                            $collection
                        )
                )
                ->values(),

            'pagination' => [
                'current_page' =>
                    $collections->currentPage(),

                'last_page' =>
                    $collections->lastPage(),

                'per_page' =>
                    $collections->perPage(),

                'total' =>
                    $collections->total(),
            ],
        ], 'Agent collections retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view agent collection summary.',
                [],
                403
            );
        }

        $query = AgentCollection::query();

        $this->restrictAgent($query, $user);

        if ($request->filled('coffee_season_id')) {
            $query->where(
                'coffee_season_id',
                $request->integer(
                    'coffee_season_id'
                )
            );
        }

        $completedIds = (clone $query)
            ->where(
                'status',
                AgentCollection::STATUS_COMPLETED
            )
            ->pluck('id');

        $purchases = CoffeePurchase::query()
            ->whereIn(
                'agent_collection_id',
                $completedIds
            )
            ->where(
                'status',
                CoffeePurchase::STATUS_APPROVED
            );

        return $this->sendResponse([
            'total_collections' =>
                (clone $query)->count(),

            'open_collections' =>
                (clone $query)
                    ->where(
                        'status',
                        AgentCollection::STATUS_OPEN
                    )
                    ->count(),

            'completed_collections' =>
                (clone $query)
                    ->where(
                        'status',
                        AgentCollection::STATUS_COMPLETED
                    )
                    ->count(),

            'cancelled_collections' =>
                (clone $query)
                    ->where(
                        'status',
                        AgentCollection::STATUS_CANCELLED
                    )
                    ->count(),

            'completed_quantity_kg' =>
                $this->money(
                    (clone $purchases)
                        ->sum('quantity_kg')
                ),

            'completed_amount' =>
                $this->money(
                    (clone $purchases)
                        ->sum('total_amount')
                ),

            'farmers_served' =>
                (clone $purchases)
                    ->distinct()
                    ->count('farmer_id'),

            'currency' => 'RWF',
        ], 'Agent collection summary retrieved successfully.');
    }

    public function lookup(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view agent collections.',
                [],
                403
            );
        }

        $query = AgentCollection::query()
            ->with($this->listRelations())
            ->where(
                'status',
                AgentCollection::STATUS_COMPLETED
            );

        $this->restrictAgent($query, $user);

        $collections = $query
            ->latest('collection_date')
            ->limit(100)
            ->get();

        return $this->sendResponse([
            'items' => $collections
                ->map(
                    fn ($collection) =>
                        $this->collectionData(
                            $collection
                        )
                )
                ->values(),
        ], 'Agent collection lookup retrieved successfully.');
    }

    public function eligiblePurchases(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view eligible purchases.',
                [],
                403
            );
        }

        if ($user->role === User::ROLE_AGENT) {
            $agent = $this->agentForUser($user);
        } else {
            $agent = Agent::query()
                ->whereKey(
                    $request->integer('agent_id')
                )
                ->where(
                    'status',
                    Agent::STATUS_ACTIVE
                )
                ->first();
        }

        if (!$agent) {
            return $this->sendError(
                'A valid active agent is required.',
                [],
                422
            );
        }

        $query = CoffeePurchase::query()
            ->with([
                'farmer:id,farmer_code,full_name,phone',
                'collectionPoint:id,name',
                'season:id,code,name',
            ])
            ->where(
                'agent_id',
                $agent->id
            )
            ->where(
                'status',
                CoffeePurchase::STATUS_APPROVED
            )
            ->whereNull(
                'agent_collection_id'
            );

        if ($request->filled('coffee_season_id')) {
            $query->where(
                'coffee_season_id',
                $request->integer(
                    'coffee_season_id'
                )
            );
        }

        if ($request->filled('collection_point_id')) {
            $query->where(
                'collection_point_id',
                $request->integer(
                    'collection_point_id'
                )
            );
        }

        $purchases = $query
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->limit(200)
            ->get();

        return $this->sendResponse([
            'items' => $purchases,
        ], 'Eligible coffee purchases retrieved successfully.');
    }

    public function store(
        StoreAgentCollectionRequest $request
    ): JsonResponse {
        $agent = $this->resolveAgent($request);

        if (!$agent) {
            return $this->sendError(
                'A valid active agent is required.',
                [],
                422
            );
        }

        $season = $this->activeSeason(
            $request->collection_date
        );

        if (!$season) {
            return $this->sendError(
                'No active coffee season covers this collection date.',
                [],
                422
            );
        }

        $collection = DB::transaction(
            function () use (
                $request,
                $agent,
                $season
            ) {
                $collection =
                    AgentCollection::create([
                        'coffee_season_id' =>
                            $season->id,

                        'agent_id' =>
                            $agent->id,

                        'collection_point_id' =>
                            $request->integer(
                                'collection_point_id'
                            ) ?: null,

                        'collection_date' =>
                            $request->collection_date,

                        'status' =>
                            AgentCollection::STATUS_OPEN,

                        'notes' =>
                            $request->notes,

                        'created_by' =>
                            $request->user()->id,
                    ]);

                if ($request->filled('purchase_ids')) {
                    $this->attachPurchases(
                        $collection,
                        $request->input(
                            'purchase_ids',
                            []
                        )
                    );
                }

                return $collection;
            }
        );

        return $this->sendResponse(
            $this->collectionData(
                $collection->fresh()
                    ->load(
                        $this->detailRelations()
                    ),
                true
            ),
            'Agent collection created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        AgentCollection $agentCollection
    ): JsonResponse {
        if (
            !$this->canAccess(
                $request->user(),
                $agentCollection
            )
        ) {
            return $this->sendError(
                'You are not allowed to view this collection.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->collectionData(
                $agentCollection->load(
                    $this->detailRelations()
                ),
                true
            ),
            'Agent collection retrieved successfully.'
        );
    }

    public function update(
        UpdateAgentCollectionRequest $request,
        AgentCollection $agentCollection
    ): JsonResponse {
        if (
            !$this->canManage(
                $request->user(),
                $agentCollection
            )
        ) {
            return $this->sendError(
                'You are not allowed to update this collection.',
                [],
                403
            );
        }

        if (
            $agentCollection->status !==
            AgentCollection::STATUS_OPEN
        ) {
            return $this->sendError(
                'Only open collections can be edited.',
                [],
                422
            );
        }

        $season = $this->activeSeason(
            $request->collection_date
        );

        if (
            !$season ||
            $season->id !==
                $agentCollection->coffee_season_id
        ) {
            return $this->sendError(
                'The collection date must remain inside the active coffee season.',
                [],
                422
            );
        }

        $agentCollection->update([
            'collection_point_id' =>
                $request->integer(
                    'collection_point_id'
                ) ?: null,

            'collection_date' =>
                $request->collection_date,

            'notes' =>
                $request->notes,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->collectionData(
                $agentCollection->fresh()
                    ->load(
                        $this->detailRelations()
                    ),
                true
            ),
            'Agent collection updated successfully.'
        );
    }

    public function addPurchases(
        AddAgentCollectionPurchasesRequest $request,
        AgentCollection $agentCollection
    ): JsonResponse {
        if (
            !$this->canManage(
                $request->user(),
                $agentCollection
            )
        ) {
            return $this->sendError(
                'You are not allowed to manage this collection.',
                [],
                403
            );
        }

        if (
            $agentCollection->status !==
            AgentCollection::STATUS_OPEN
        ) {
            return $this->sendError(
                'Purchases can only be added to an open collection.',
                [],
                422
            );
        }

        DB::transaction(function () use (
            $agentCollection,
            $request
        ) {
            $this->attachPurchases(
                $agentCollection,
                $request->purchase_ids
            );
        });

        return $this->sendResponse(
            $this->collectionData(
                $agentCollection->fresh()
                    ->load(
                        $this->detailRelations()
                    ),
                true
            ),
            'Coffee purchases added successfully.'
        );
    }

    public function removePurchase(
        Request $request,
        AgentCollection $agentCollection,
        CoffeePurchase $coffeePurchase
    ): JsonResponse {
        if (
            !$this->canManage(
                $request->user(),
                $agentCollection
            )
        ) {
            return $this->sendError(
                'You are not allowed to manage this collection.',
                [],
                403
            );
        }

        if (
            $agentCollection->status !==
            AgentCollection::STATUS_OPEN
        ) {
            return $this->sendError(
                'Purchases cannot be removed from a completed collection.',
                [],
                422
            );
        }

        if (
            $coffeePurchase->agent_collection_id !==
            $agentCollection->id
        ) {
            return $this->sendError(
                'This purchase does not belong to the selected collection.',
                [],
                422
            );
        }

        $coffeePurchase->update([
            'agent_collection_id' => null,
            'updated_by' => $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->collectionData(
                $agentCollection->fresh()
                    ->load(
                        $this->detailRelations()
                    ),
                true
            ),
            'Coffee purchase removed successfully.'
        );
    }

    public function complete(
        Request $request,
        AgentCollection $agentCollection
    ): JsonResponse {
        if (
            !$this->canManage(
                $request->user(),
                $agentCollection
            )
        ) {
            return $this->sendError(
                'You are not allowed to complete this collection.',
                [],
                403
            );
        }

        if (
            $agentCollection->status !==
            AgentCollection::STATUS_OPEN
        ) {
            return $this->sendError(
                'Only open collections can be completed.',
                [],
                422
            );
        }

        $purchaseCount =
            $agentCollection->purchases()
                ->where(
                    'status',
                    CoffeePurchase::STATUS_APPROVED
                )
                ->count();

        if ($purchaseCount === 0) {
            return $this->sendError(
                'At least one approved coffee purchase is required before completing the collection.',
                [],
                422
            );
        }

        $agentCollection->update([
            'status' =>
                AgentCollection::STATUS_COMPLETED,

            'completed_by' =>
                $request->user()->id,

            'completed_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->collectionData(
                $agentCollection->fresh()
                    ->load(
                        $this->detailRelations()
                    ),
                true
            ),
            'Agent collection completed successfully.'
        );
    }

    public function cancel(
        CancelAgentCollectionRequest $request,
        AgentCollection $agentCollection
    ): JsonResponse {
        if (
            !$this->canManage(
                $request->user(),
                $agentCollection
            )
        ) {
            return $this->sendError(
                'You are not allowed to cancel this collection.',
                [],
                403
            );
        }

        if (
            $agentCollection->status !==
            AgentCollection::STATUS_OPEN
        ) {
            return $this->sendError(
                'Only open collections can be cancelled.',
                [],
                422
            );
        }

        DB::transaction(function () use (
            $request,
            $agentCollection
        ) {
            $agentCollection->purchases()
                ->update([
                    'agent_collection_id' =>
                        null,
                ]);

            $agentCollection->update([
                'status' =>
                    AgentCollection::STATUS_CANCELLED,

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
        });

        return $this->sendResponse(
            $this->collectionData(
                $agentCollection->fresh()
                    ->load(
                        $this->detailRelations()
                    ),
                true
            ),
            'Agent collection cancelled successfully.'
        );
    }

    private function attachPurchases(
        AgentCollection $collection,
        array $ids
    ): void {
        $purchases = CoffeePurchase::query()
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get();

        foreach ($purchases as $purchase) {
            if (
                $purchase->status !==
                CoffeePurchase::STATUS_APPROVED
            ) {
                throw ValidationException::withMessages([
                    'purchase_ids' =>
                        "Purchase {$purchase->purchase_code} must be approved first.",
                ]);
            }

            if (
                $purchase->agent_id !==
                $collection->agent_id
            ) {
                throw ValidationException::withMessages([
                    'purchase_ids' =>
                        "Purchase {$purchase->purchase_code} belongs to another agent.",
                ]);
            }

            if (
                $purchase->coffee_season_id !==
                $collection->coffee_season_id
            ) {
                throw ValidationException::withMessages([
                    'purchase_ids' =>
                        "Purchase {$purchase->purchase_code} belongs to another coffee season.",
                ]);
            }

            if (
                $purchase->agent_collection_id &&
                $purchase->agent_collection_id !==
                    $collection->id
            ) {
                throw ValidationException::withMessages([
                    'purchase_ids' =>
                        "Purchase {$purchase->purchase_code} already belongs to another collection.",
                ]);
            }

            if (
                $collection->collection_point_id &&
                $purchase->collection_point_id &&
                $purchase->collection_point_id !==
                    $collection->collection_point_id
            ) {
                throw ValidationException::withMessages([
                    'purchase_ids' =>
                        "Purchase {$purchase->purchase_code} belongs to another collection point.",
                ]);
            }
        }

        if (!$collection->collection_point_id) {
            $points = $purchases
                ->pluck('collection_point_id')
                ->filter()
                ->unique()
                ->values();

            if ($points->count() > 1) {
                throw ValidationException::withMessages([
                    'purchase_ids' =>
                        'Selected purchases belong to different collection points.',
                ]);
            }

            if ($points->count() === 1) {
                $collection->update([
                    'collection_point_id' =>
                        $points->first(),
                ]);
            }
        }

        CoffeePurchase::query()
            ->whereIn('id', $ids)
            ->update([
                'agent_collection_id' =>
                    $collection->id,
            ]);
    }

    private function activeSeason(
        string $date
    ): ?CoffeeSeason {
        return CoffeeSeason::query()
            ->where(
                'status',
                CoffeeSeason::STATUS_ACTIVE
            )
            ->whereDate(
                'start_date',
                '<=',
                $date
            )
            ->whereDate(
                'end_date',
                '>=',
                $date
            )
            ->first();
    }

    private function resolveAgent(
        Request $request
    ): ?Agent {
        $user = $request->user();

        if ($user->role === User::ROLE_AGENT) {
            return $this->agentForUser($user);
        }

        if (
            $user->role !== User::ROLE_ADMIN ||
            !$request->filled('agent_id')
        ) {
            return null;
        }

        return Agent::query()
            ->whereKey(
                $request->integer('agent_id')
            )
            ->where(
                'status',
                Agent::STATUS_ACTIVE
            )
            ->whereHas(
                'user',
                fn ($query) =>
                    $query->where(
                        'status',
                        'active'
                    )
            )
            ->first();
    }

    private function agentForUser(
        User $user
    ): ?Agent {
        return Agent::query()
            ->where('user_id', $user->id)
            ->where(
                'status',
                Agent::STATUS_ACTIVE
            )
            ->first();
    }

    private function canRead(
        ?User $user
    ): bool {
        return $user &&
            in_array($user->role, [
                User::ROLE_ADMIN,
                User::ROLE_ACCOUNTANT,
                User::ROLE_AGENT,
            ], true);
    }

    private function canAccess(
        User $user,
        AgentCollection $collection
    ): bool {
        if (
            in_array($user->role, [
                User::ROLE_ADMIN,
                User::ROLE_ACCOUNTANT,
            ], true)
        ) {
            return true;
        }

        $agent = $this->agentForUser($user);

        return $agent &&
            $agent->id ===
                $collection->agent_id;
    }

    private function canManage(
        User $user,
        AgentCollection $collection
    ): bool {
        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        if ($user->role !== User::ROLE_AGENT) {
            return false;
        }

        $agent = $this->agentForUser($user);

        return $agent &&
            $agent->id ===
                $collection->agent_id;
    }

    private function restrictAgent(
        $query,
        User $user
    ): void {
        if ($user->role !== User::ROLE_AGENT) {
            return;
        }

        $agent = $this->agentForUser($user);

        $query->where(
            'agent_id',
            $agent?->id ?? 0
        );
    }

    private function listRelations(): array
    {
        return [
            'season:id,code,name,status',
            'agent.user:id,name,email,phone',
            'collectionPoint:id,name',
            'purchases:id,agent_collection_id,farmer_id,quantity_kg,total_amount,status',
        ];
    }

    private function detailRelations(): array
    {
        return [
            'season:id,code,name,status',
            'agent.user:id,name,email,phone',
            'collectionPoint:id,name',
            'purchases.farmer:id,farmer_code,full_name,phone',
            'creator:id,name',
            'updater:id,name',
            'completer:id,name',
            'canceller:id,name',
        ];
    }

    private function collectionData(
        AgentCollection $collection,
        bool $includePurchases = false
    ): array {
        $purchases = $collection->purchases
            ->where(
                'status',
                CoffeePurchase::STATUS_APPROVED
            );

        $data = [
            'id' => $collection->id,

            'collection_code' =>
                $collection->collection_code,

            'coffee_season_id' =>
                $collection->coffee_season_id,

            'agent_id' =>
                $collection->agent_id,

            'collection_point_id' =>
                $collection->collection_point_id,

            'collection_date' =>
                $collection->collection_date
                    ?->format('Y-m-d'),

            'status' =>
                $collection->status,

            'notes' =>
                $collection->notes,

            'purchases_count' =>
                $purchases->count(),

            'farmers_count' =>
                $purchases
                    ->pluck('farmer_id')
                    ->unique()
                    ->count(),

            'total_quantity_kg' =>
                $this->money(
                    $purchases->sum(
                        'quantity_kg'
                    )
                ),

            'total_amount' =>
                $this->money(
                    $purchases->sum(
                        'total_amount'
                    )
                ),

            'currency' => 'RWF',

            'season' =>
                $collection->season,

            'agent' =>
                $collection->agent,

            'collection_point' =>
                $collection->collectionPoint,

            'completed_at' =>
                $collection->completed_at,

            'cancelled_at' =>
                $collection->cancelled_at,

            'cancellation_reason' =>
                $collection
                    ->cancellation_reason,
        ];

        if ($includePurchases) {
            $data['purchases'] =
                $purchases->values();

            $data['creator'] =
                $collection->creator;

            $data['updater'] =
                $collection->updater;

            $data['completer'] =
                $collection->completer;

            $data['canceller'] =
                $collection->canceller;
        }

        return $data;
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
