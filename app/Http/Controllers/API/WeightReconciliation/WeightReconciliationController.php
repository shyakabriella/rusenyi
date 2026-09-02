<?php

namespace App\Http\Controllers\API\WeightReconciliation;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\WeightReconciliation\CancelWeightReconciliationRequest;
use App\Http\Requests\API\WeightReconciliation\StoreWeightReconciliationRequest;
use App\Http\Requests\API\WeightReconciliation\UpdateWeightReconciliationRequest;
use App\Models\FactoryReception;
use App\Models\User;
use App\Models\WeightReconciliation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WeightReconciliationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view weight reconciliations.',
                [],
                403
            );
        }

        $query = WeightReconciliation::query()
            ->with($this->relations());

        $this->applyScope(
            $query,
            $user
        );

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->search
            );

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where(
                        'reconciliation_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'factoryReception',
                        fn (Builder $reception) =>
                            $reception->where(
                                'reception_code',
                                'like',
                                "%{$search}%"
                            )
                    )
                    ->orWhereHas(
                        'agent.user',
                        fn (Builder $user) =>
                            $user->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                    );
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        if ($request->filled('outcome')) {
            $query->where(
                'outcome',
                $request->outcome
            );
        }

        if ($request->filled('coffee_season_id')) {
            $query->where(
                'coffee_season_id',
                $request->integer(
                    'coffee_season_id'
                )
            );
        }

        if ($request->filled('agent_id')) {
            $query->where(
                'agent_id',
                $request->integer(
                    'agent_id'
                )
            );
        }

        $items = $query
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
                $items->items()
            )
                ->map(
                    fn (WeightReconciliation $item) =>
                        $this->data($item)
                )
                ->values(),

            'pagination' => [
                'current_page' =>
                    $items->currentPage(),

                'last_page' =>
                    $items->lastPage(),

                'per_page' =>
                    $items->perPage(),

                'total' =>
                    $items->total(),
            ],
        ], 'Weight reconciliations retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view reconciliation summary.',
                [],
                403
            );
        }

        $query =
            WeightReconciliation::query();

        $this->applyScope(
            $query,
            $user
        );

        $active = clone $query;

        $active->where(
            'status',
            '!=',
            WeightReconciliation::STATUS_CANCELLED
        );

        return $this->sendResponse([
            'total_reconciliations' =>
                (clone $active)->count(),

            'draft_reconciliations' =>
                (clone $active)
                    ->where(
                        'status',
                        WeightReconciliation::STATUS_DRAFT
                    )
                    ->count(),

            'reconciled_reconciliations' =>
                (clone $active)
                    ->where(
                        'status',
                        WeightReconciliation::STATUS_RECONCILED
                    )
                    ->count(),

            'within_tolerance' =>
                (clone $active)
                    ->where(
                        'outcome',
                        WeightReconciliation::OUTCOME_WITHIN_TOLERANCE
                    )
                    ->count(),

            'shortages' =>
                (clone $active)
                    ->where(
                        'outcome',
                        WeightReconciliation::OUTCOME_SHORTAGE
                    )
                    ->count(),

            'excesses' =>
                (clone $active)
                    ->where(
                        'outcome',
                        WeightReconciliation::OUTCOME_EXCESS
                    )
                    ->count(),

            'net_difference_kg' =>
                $this->decimal(
                    (clone $active)
                        ->where(
                            'status',
                            WeightReconciliation::STATUS_RECONCILED
                        )
                        ->sum(
                            'difference_kg'
                        )
                ),

            'absolute_variance_kg' =>
                $this->decimal(
                    (clone $active)
                        ->where(
                            'status',
                            WeightReconciliation::STATUS_RECONCILED
                        )
                        ->get(
                            'difference_kg'
                        )
                        ->sum(
                            fn ($item) =>
                                abs(
                                    (float) $item->difference_kg
                                )
                        )
                ),
        ], 'Reconciliation summary retrieved successfully.');
    }

    public function eligibleReceptions(
        Request $request
    ): JsonResponse {
        if (
            !in_array(
                $request->user()->role,
                ['admin', 'balance'],
                true
            )
        ) {
            return $this->sendError(
                'You are not allowed to view eligible factory receptions.',
                [],
                403
            );
        }

        $query = FactoryReception::query()
            ->with([
                'season',
                'agentCollection',
                'fieldWeighing',
                'agent.user',
                'collectionPoint',
            ])
            ->where(
                'status',
                FactoryReception::STATUS_CONFIRMED
            )
            ->whereDoesntHave(
                'weightReconciliations',
                fn (Builder $query) =>
                    $query->where(
                        'status',
                        '!=',
                        WeightReconciliation::STATUS_CANCELLED
                    )
            );

        if (
            $request->user()->role ===
            'balance'
        ) {
            $query->where(
                'balance_officer_id',
                $request->user()->id
            );
        }

        $items = $query
            ->latest('id')
            ->limit(200)
            ->get();

        return $this->sendResponse([
            'items' =>
                $items
                    ->map(
                        fn (FactoryReception $reception) =>
                            $this->receptionData(
                                $reception
                            )
                    )
                    ->values(),
        ], 'Eligible factory receptions retrieved successfully.');
    }

    public function store(
        StoreWeightReconciliationRequest $request
    ): JsonResponse {
        $reconciliation = DB::transaction(
            function () use ($request) {
                $reception =
                    FactoryReception::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $request->integer(
                                'factory_reception_id'
                            )
                        );

                if (
                    $reception->status !==
                    FactoryReception::STATUS_CONFIRMED
                ) {
                    abort(
                        422,
                        'Only confirmed Factory Receptions can be reconciled.'
                    );
                }

                if (
                    $request->user()->role ===
                        'balance' &&
                    $reception->balance_officer_id !==
                        $request->user()->id
                ) {
                    abort(
                        403,
                        'Balance Officers can only reconcile their own Factory Receptions.'
                    );
                }

                $exists =
                    WeightReconciliation::query()
                        ->where(
                            'factory_reception_id',
                            $reception->id
                        )
                        ->where(
                            'status',
                            '!=',
                            WeightReconciliation::STATUS_CANCELLED
                        )
                        ->exists();

                if ($exists) {
                    abort(
                        422,
                        'This Factory Reception already has an active Weight Reconciliation.'
                    );
                }

                $fieldWeight =
                    (float) $reception->field_weight_kg;

                $factoryWeight =
                    (float) $reception->factory_weight_kg;

                $difference =
                    $factoryWeight -
                    $fieldWeight;

                $percentage =
                    $fieldWeight > 0
                        ? (
                            $difference /
                            $fieldWeight
                        ) * 100
                        : 0;

                return WeightReconciliation::create([
                    'factory_reception_id' =>
                        $reception->id,

                    'coffee_season_id' =>
                        $reception->coffee_season_id,

                    'agent_collection_id' =>
                        $reception->agent_collection_id,

                    'field_weighing_id' =>
                        $reception->field_weighing_id,

                    'agent_id' =>
                        $reception->agent_id,

                    'collection_point_id' =>
                        $reception->collection_point_id,

                    'field_weight_kg' =>
                        $fieldWeight,

                    'factory_weight_kg' =>
                        $factoryWeight,

                    'difference_kg' =>
                        round(
                            $difference,
                            2
                        ),

                    'difference_percentage' =>
                        round(
                            $percentage,
                            4
                        ),

                    'tolerance_percentage' =>
                        $request->input(
                            'tolerance_percentage',
                            2
                        ),

                    'status' =>
                        WeightReconciliation::STATUS_DRAFT,

                    'notes' =>
                        $request->notes,

                    'created_by' =>
                        $request->user()->id,
                ]);
            }
        );

        return $this->sendResponse(
            $this->data(
                $reconciliation
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Weight reconciliation created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        WeightReconciliation $weightReconciliation
    ): JsonResponse {
        if (
            !$this->canAccess(
                $request->user(),
                $weightReconciliation
            )
        ) {
            return $this->sendError(
                'You are not allowed to view this Weight Reconciliation.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $weightReconciliation
                    ->load(
                        $this->relations()
                    )
            ),
            'Weight reconciliation retrieved successfully.'
        );
    }

    public function update(
        UpdateWeightReconciliationRequest $request,
        WeightReconciliation $weightReconciliation
    ): JsonResponse {
        if (
            $weightReconciliation->status !==
            WeightReconciliation::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft Weight Reconciliations can be updated.',
                [],
                422
            );
        }

        if (
            !$this->canManage(
                $request->user(),
                $weightReconciliation
            )
        ) {
            return $this->sendError(
                'You are not allowed to update this Weight Reconciliation.',
                [],
                403
            );
        }

        $weightReconciliation->update([
            'tolerance_percentage' =>
                $request->has(
                    'tolerance_percentage'
                )
                    ? $request->tolerance_percentage
                    : $weightReconciliation
                        ->tolerance_percentage,

            'notes' =>
                $request->has('notes')
                    ? $request->notes
                    : $weightReconciliation->notes,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $weightReconciliation
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Weight reconciliation updated successfully.'
        );
    }

    public function reconcile(
        Request $request,
        WeightReconciliation $weightReconciliation
    ): JsonResponse {
        if (
            !$this->canManage(
                $request->user(),
                $weightReconciliation
            )
        ) {
            return $this->sendError(
                'You are not allowed to reconcile this record.',
                [],
                403
            );
        }

        if (
            $weightReconciliation->status !==
            WeightReconciliation::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft Weight Reconciliations can be reconciled.',
                [],
                422
            );
        }

        $difference =
            (float) $weightReconciliation
                ->difference_kg;

        $differencePercentage =
            abs(
                (float) $weightReconciliation
                    ->difference_percentage
            );

        $tolerance =
            (float) $weightReconciliation
                ->tolerance_percentage;

        if (
            $differencePercentage <=
            $tolerance
        ) {
            $outcome =
                WeightReconciliation::OUTCOME_WITHIN_TOLERANCE;
        } elseif ($difference < 0) {
            $outcome =
                WeightReconciliation::OUTCOME_SHORTAGE;
        } else {
            $outcome =
                WeightReconciliation::OUTCOME_EXCESS;
        }

        $weightReconciliation->update([
            'status' =>
                WeightReconciliation::STATUS_RECONCILED,

            'outcome' =>
                $outcome,

            'reconciled_by' =>
                $request->user()->id,

            'reconciled_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $weightReconciliation
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Weight reconciliation completed successfully.'
        );
    }

    public function cancel(
        CancelWeightReconciliationRequest $request,
        WeightReconciliation $weightReconciliation
    ): JsonResponse {
        if (
            !$this->canManage(
                $request->user(),
                $weightReconciliation
            )
        ) {
            return $this->sendError(
                'You are not allowed to cancel this Weight Reconciliation.',
                [],
                403
            );
        }

        if (
            $weightReconciliation->status !==
            WeightReconciliation::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft Weight Reconciliations can be cancelled.',
                [],
                422
            );
        }

        $weightReconciliation->update([
            'status' =>
                WeightReconciliation::STATUS_CANCELLED,

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
            $this->data(
                $weightReconciliation
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Weight reconciliation cancelled successfully.'
        );
    }

    private function canRead(?User $user): bool
    {
        return $user &&
            in_array(
                $user->role,
                [
                    'admin',
                    'accountant',
                    'balance',
                    'agent',
                ],
                true
            );
    }

    private function canAccess(
        User $user,
        WeightReconciliation $reconciliation
    ): bool {
        if (
            in_array(
                $user->role,
                [
                    'admin',
                    'accountant',
                ],
                true
            )
        ) {
            return true;
        }

        if ($user->role === 'balance') {
            return $reconciliation
                ->factoryReception
                ?->balance_officer_id ===
                $user->id;
        }

        if ($user->role === 'agent') {
            return $reconciliation
                ->agent
                ?->user_id ===
                $user->id;
        }

        return false;
    }

    private function canManage(
        User $user,
        WeightReconciliation $reconciliation
    ): bool {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role !== 'balance') {
            return false;
        }

        return $reconciliation
            ->factoryReception
            ?->balance_officer_id ===
            $user->id;
    }

    private function applyScope(
        Builder $query,
        User $user
    ): void {
        if (
            in_array(
                $user->role,
                ['admin', 'accountant'],
                true
            )
        ) {
            return;
        }

        if ($user->role === 'balance') {
            $query->whereHas(
                'factoryReception',
                fn (Builder $reception) =>
                    $reception->where(
                        'balance_officer_id',
                        $user->id
                    )
            );

            return;
        }

        if ($user->role === 'agent') {
            $query->whereHas(
                'agent',
                fn (Builder $agent) =>
                    $agent->where(
                        'user_id',
                        $user->id
                    )
            );
        }
    }

    private function relations(): array
    {
        return [
            'factoryReception',
            'season',
            'agentCollection',
            'fieldWeighing',
            'agent.user',
            'collectionPoint',
            'creator:id,name',
            'updater:id,name',
            'reconciler:id,name',
            'canceller:id,name',
        ];
    }

    private function data(
        WeightReconciliation $item
    ): array {
        return [
            'id' =>
                $item->id,

            'reconciliation_code' =>
                $item->reconciliation_code,

            'factory_reception_id' =>
                $item->factory_reception_id,

            'coffee_season_id' =>
                $item->coffee_season_id,

            'agent_collection_id' =>
                $item->agent_collection_id,

            'field_weighing_id' =>
                $item->field_weighing_id,

            'agent_id' =>
                $item->agent_id,

            'collection_point_id' =>
                $item->collection_point_id,

            'field_weight_kg' =>
                $item->field_weight_kg,

            'factory_weight_kg' =>
                $item->factory_weight_kg,

            'difference_kg' =>
                $item->difference_kg,

            'difference_percentage' =>
                $item->difference_percentage,

            'tolerance_percentage' =>
                $item->tolerance_percentage,

            'outcome' =>
                $item->outcome,

            'status' =>
                $item->status,

            'notes' =>
                $item->notes,

            'factory_reception' =>
                $item->factoryReception,

            'season' =>
                $item->season,

            'agent_collection' =>
                $item->agentCollection,

            'field_weighing' =>
                $item->fieldWeighing,

            'agent' =>
                $item->agent,

            'collection_point' =>
                $item->collectionPoint,

            'creator' =>
                $item->creator,

            'updater' =>
                $item->updater,

            'reconciler' =>
                $item->reconciler,

            'reconciled_at' =>
                $item->reconciled_at
                    ?->toISOString(),

            'canceller' =>
                $item->canceller,

            'cancelled_at' =>
                $item->cancelled_at
                    ?->toISOString(),

            'cancellation_reason' =>
                $item->cancellation_reason,
        ];
    }

    private function receptionData(
        FactoryReception $reception
    ): array {
        return [
            'id' =>
                $reception->id,

            'reception_code' =>
                $reception->reception_code,

            'coffee_season_id' =>
                $reception->coffee_season_id,

            'agent_collection_id' =>
                $reception->agent_collection_id,

            'field_weighing_id' =>
                $reception->field_weighing_id,

            'agent_id' =>
                $reception->agent_id,

            'collection_point_id' =>
                $reception->collection_point_id,

            'field_weight_kg' =>
                $reception->field_weight_kg,

            'factory_weight_kg' =>
                $reception->factory_weight_kg,

            'difference_kg' =>
                $reception->difference_kg,

            'difference_percentage' =>
                $reception->difference_percentage,

            'received_at' =>
                $reception->received_at,

            'season' =>
                $reception->season,

            'agent_collection' =>
                $reception->agentCollection,

            'field_weighing' =>
                $reception->fieldWeighing,

            'agent' =>
                $reception->agent,

            'collection_point' =>
                $reception->collectionPoint,
        ];
    }

    private function decimal($value): string
    {
        return number_format(
            (float) $value,
            2,
            '.',
            ''
        );
    }
}
