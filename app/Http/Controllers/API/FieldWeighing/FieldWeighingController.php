<?php

namespace App\Http\Controllers\API\FieldWeighing;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\FieldWeighing\CancelFieldWeighingRequest;
use App\Http\Requests\API\FieldWeighing\StoreFieldWeighingRequest;
use App\Http\Requests\API\FieldWeighing\UpdateFieldWeighingRequest;
use App\Models\Agent;
use App\Models\AgentCollection;
use App\Models\CoffeePurchase;
use App\Models\FieldWeighing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FieldWeighingController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view field weighings.',
                [],
                403
            );
        }

        $query = FieldWeighing::query()
            ->with($this->relations());

        $this->restrictReadScope(
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
                        'weighing_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'agentCollection',
                        fn (Builder $collection) =>
                            $collection->where(
                                'collection_code',
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
                    )
                    ->orWhereHas(
                        'balanceOfficer',
                        fn (Builder $officer) =>
                            $officer->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                    );
            });
        }

        foreach ([
            'status',
            'agent_id',
            'balance_officer_id',
            'coffee_season_id',
            'collection_point_id',
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
                'weighed_at',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'weighed_at',
                '<=',
                $request->date_to
            );
        }

        $weighings = $query
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
                $weighings->items()
            )
                ->map(
                    fn (FieldWeighing $weighing) =>
                        $this->data($weighing)
                )
                ->values(),

            'pagination' => [
                'current_page' =>
                    $weighings->currentPage(),

                'last_page' =>
                    $weighings->lastPage(),

                'per_page' =>
                    $weighings->perPage(),

                'total' =>
                    $weighings->total(),
            ],
        ], 'Field weighings retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view field weighing summary.',
                [],
                403
            );
        }

        $query = FieldWeighing::query();

        $this->restrictReadScope(
            $query,
            $user
        );

        if ($request->filled('coffee_season_id')) {
            $query->where(
                'coffee_season_id',
                $request->integer(
                    'coffee_season_id'
                )
            );
        }

        $confirmed = (clone $query)
            ->where(
                'status',
                FieldWeighing::STATUS_CONFIRMED
            );

        $difference = (float) (
            clone $confirmed
        )->sum('difference_kg');

        return $this->sendResponse([
            'total_records' =>
                (clone $query)->count(),

            'draft_records' =>
                (clone $query)
                    ->where(
                        'status',
                        FieldWeighing::STATUS_DRAFT
                    )
                    ->count(),

            'confirmed_records' =>
                (clone $query)
                    ->where(
                        'status',
                        FieldWeighing::STATUS_CONFIRMED
                    )
                    ->count(),

            'cancelled_records' =>
                (clone $query)
                    ->where(
                        'status',
                        FieldWeighing::STATUS_CANCELLED
                    )
                    ->count(),

            'expected_quantity_kg' =>
                $this->decimal(
                    (clone $confirmed)
                        ->sum(
                            'expected_quantity_kg'
                        )
                ),

            'field_weight_kg' =>
                $this->decimal(
                    (clone $confirmed)
                        ->sum(
                            'field_weight_kg'
                        )
                ),

            'difference_kg' =>
                $this->decimal($difference),

            'shortage_quantity_kg' =>
                $this->decimal(
                    $difference < 0
                        ? abs($difference)
                        : 0
                ),
        ], 'Field weighing summary retrieved successfully.');
    }

    public function eligibleCollections(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        if ($user->role !== 'balance') {
            return $this->sendError(
                'Only Balance Officers can access collections awaiting field weighing.',
                [],
                403
            );
        }

        $query = AgentCollection::query()
            ->with([
                'season:id,code,name,status',
                'agent.user:id,name,email,phone',
                'collectionPoint:id,name',
                'purchases:id,agent_collection_id,farmer_id,quantity_kg,total_amount,status',
            ])
            ->where(
                'status',
                AgentCollection::STATUS_COMPLETED
            )
            ->whereDoesntHave(
                'fieldWeighings',
                fn (Builder $weighing) =>
                    $weighing->where(
                        'status',
                        '!=',
                        FieldWeighing::STATUS_CANCELLED
                    )
            );

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

        $collections = $query
            ->latest('collection_date')
            ->limit(100)
            ->get();

        return $this->sendResponse([
            'items' => $collections
                ->map(function (
                    AgentCollection $collection
                ) {
                    $expected =
                        $this->expectedQuantity(
                            $collection
                        );

                    return [
                        'id' => $collection->id,

                        'collection_code' =>
                            $collection
                                ->collection_code,

                        'coffee_season_id' =>
                            $collection
                                ->coffee_season_id,

                        'agent_id' =>
                            $collection
                                ->agent_id,

                        'collection_point_id' =>
                            $collection
                                ->collection_point_id,

                        'collection_date' =>
                            $collection
                                ->collection_date
                                ?->format('Y-m-d'),

                        'expected_quantity_kg' =>
                            $this->decimal(
                                $expected
                            ),

                        'agent' =>
                            $collection->agent,

                        'collection_point' =>
                            $collection
                                ->collectionPoint,

                        'season' =>
                            $collection->season,
                    ];
                })
                ->values(),
        ], 'Collections awaiting field weighing retrieved successfully.');
    }

    public function store(
        StoreFieldWeighingRequest $request
    ): JsonResponse {
        $collection = AgentCollection::query()
            ->with([
                'purchases',
                'agent',
                'collectionPoint',
            ])
            ->find(
                $request->integer(
                    'agent_collection_id'
                )
            );

        if (!$collection) {
            return $this->sendError(
                'Agent collection was not found.',
                [],
                422
            );
        }

        if (
            $collection->status !==
            AgentCollection::STATUS_COMPLETED
        ) {
            return $this->sendError(
                'Only completed agent collections can be weighed in the field.',
                [],
                422
            );
        }

        $existing = FieldWeighing::query()
            ->where(
                'agent_collection_id',
                $collection->id
            )
            ->where(
                'status',
                '!=',
                FieldWeighing::STATUS_CANCELLED
            )
            ->exists();

        if ($existing) {
            return $this->sendError(
                'This agent collection already has an active field weighing.',
                [],
                422
            );
        }

        $expected =
            $this->expectedQuantity(
                $collection
            );

        if ($expected <= 0) {
            return $this->sendError(
                'This collection has no approved coffee quantity to weigh.',
                [],
                422
            );
        }

        if (
            Carbon::parse(
                $request->weighed_at
            )->startOfDay()->lt(
                Carbon::parse(
                    $collection->collection_date
                )->startOfDay()
            )
        ) {
            return $this->sendError(
                'Field weighing cannot happen before the collection date.',
                [],
                422
            );
        }

        $fieldWeight = (float)
            $request->field_weight_kg;

        [$difference, $percentage] =
            $this->difference(
                $expected,
                $fieldWeight
            );

        $weighing = DB::transaction(
            function () use (
                $request,
                $collection,
                $expected,
                $fieldWeight,
                $difference,
                $percentage
            ) {
                return FieldWeighing::create([
                    'coffee_season_id' =>
                        $collection
                            ->coffee_season_id,

                    'agent_collection_id' =>
                        $collection->id,

                    'agent_id' =>
                        $collection->agent_id,

                    'collection_point_id' =>
                        $collection
                            ->collection_point_id,

                    'balance_officer_id' =>
                        $request->user()->id,

                    'expected_quantity_kg' =>
                        $expected,

                    'field_weight_kg' =>
                        $fieldWeight,

                    'difference_kg' =>
                        $difference,

                    'difference_percentage' =>
                        $percentage,

                    'bag_count' =>
                        $request->integer(
                            'bag_count'
                        ) ?: null,

                    'weighed_at' =>
                        $request->weighed_at,

                    'status' =>
                        FieldWeighing::STATUS_DRAFT,

                    'notes' =>
                        $request->notes,

                    'created_by' =>
                        $request->user()->id,
                ]);
            }
        );

        return $this->sendResponse(
            $this->data(
                $weighing->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Field weighing created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        FieldWeighing $fieldWeighing
    ): JsonResponse {
        if (
            !$this->canAccess(
                $request->user(),
                $fieldWeighing
            )
        ) {
            return $this->sendError(
                'You are not allowed to view this field weighing.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $fieldWeighing->load(
                    $this->relations()
                )
            ),
            'Field weighing retrieved successfully.'
        );
    }

    public function update(
        UpdateFieldWeighingRequest $request,
        FieldWeighing $fieldWeighing
    ): JsonResponse {
        if (
            !$this->ownsWeighing(
                $request->user(),
                $fieldWeighing
            )
        ) {
            return $this->sendError(
                'You can only update your own field weighing.',
                [],
                403
            );
        }

        if (
            $fieldWeighing->status !==
            FieldWeighing::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft field weighings can be edited.',
                [],
                422
            );
        }

        $collection =
            $fieldWeighing
                ->agentCollection;

        if (
            Carbon::parse(
                $request->weighed_at
            )->startOfDay()->lt(
                Carbon::parse(
                    $collection->collection_date
                )->startOfDay()
            )
        ) {
            return $this->sendError(
                'Field weighing cannot happen before the collection date.',
                [],
                422
            );
        }

        $expected = (float)
            $fieldWeighing
                ->expected_quantity_kg;

        $fieldWeight = (float)
            $request->field_weight_kg;

        [$difference, $percentage] =
            $this->difference(
                $expected,
                $fieldWeight
            );

        $fieldWeighing->update([
            'field_weight_kg' =>
                $fieldWeight,

            'difference_kg' =>
                $difference,

            'difference_percentage' =>
                $percentage,

            'bag_count' =>
                $request->integer(
                    'bag_count'
                ) ?: null,

            'weighed_at' =>
                $request->weighed_at,

            'notes' =>
                $request->notes,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $fieldWeighing->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Field weighing updated successfully.'
        );
    }

    public function confirm(
        Request $request,
        FieldWeighing $fieldWeighing
    ): JsonResponse {
        if (
            !$this->ownsWeighing(
                $request->user(),
                $fieldWeighing
            )
        ) {
            return $this->sendError(
                'You can only confirm your own field weighing.',
                [],
                403
            );
        }

        if (
            $fieldWeighing->status !==
            FieldWeighing::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft field weighings can be confirmed.',
                [],
                422
            );
        }

        $fieldWeighing->update([
            'status' =>
                FieldWeighing::STATUS_CONFIRMED,

            'confirmed_by' =>
                $request->user()->id,

            'confirmed_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $fieldWeighing->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Field weighing confirmed successfully.'
        );
    }

    public function cancel(
        CancelFieldWeighingRequest $request,
        FieldWeighing $fieldWeighing
    ): JsonResponse {
        if (
            !$this->ownsWeighing(
                $request->user(),
                $fieldWeighing
            )
        ) {
            return $this->sendError(
                'You can only cancel your own field weighing.',
                [],
                403
            );
        }

        if (
            $fieldWeighing->status ===
            FieldWeighing::STATUS_CANCELLED
        ) {
            return $this->sendError(
                'This field weighing is already cancelled.',
                [],
                422
            );
        }

        $fieldWeighing->update([
            'status' =>
                FieldWeighing::STATUS_CANCELLED,

            'cancelled_by' =>
                $request->user()->id,

            'cancelled_at' =>
                now(),

            'cancellation_reason' =>
                trim(
                    $request
                        ->cancellation_reason
                ),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $fieldWeighing->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Field weighing cancelled successfully.'
        );
    }

    private function expectedQuantity(
        AgentCollection $collection
    ): float {
        return (float)
            $collection
                ->purchases()
                ->where(
                    'status',
                    CoffeePurchase::STATUS_APPROVED
                )
                ->sum('quantity_kg');
    }

    private function difference(
        float $expected,
        float $actual
    ): array {
        $difference =
            round(
                $actual - $expected,
                2
            );

        $percentage =
            $expected > 0
                ? round(
                    (
                        $difference /
                        $expected
                    ) * 100,
                    4
                )
                : 0;

        return [
            $difference,
            $percentage,
        ];
    }

    private function canRead(
        ?User $user
    ): bool {
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
        FieldWeighing $weighing
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
            return
                $weighing
                    ->balance_officer_id ===
                $user->id;
        }

        if ($user->role === 'agent') {
            $agent =
                $this->agentForUser(
                    $user
                );

            return $agent &&
                $agent->id ===
                    $weighing->agent_id;
        }

        return false;
    }

    private function ownsWeighing(
        User $user,
        FieldWeighing $weighing
    ): bool {
        return
            $user->role === 'balance' &&
            $weighing
                ->balance_officer_id ===
                $user->id;
    }

    private function restrictReadScope(
        Builder $query,
        User $user
    ): void {
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
            return;
        }

        if ($user->role === 'balance') {
            $query->where(
                'balance_officer_id',
                $user->id
            );

            return;
        }

        if ($user->role === 'agent') {
            $agent =
                $this->agentForUser(
                    $user
                );

            $query->where(
                'agent_id',
                $agent?->id ?? 0
            );
        }
    }

    private function agentForUser(
        User $user
    ): ?Agent {
        return Agent::query()
            ->where(
                'user_id',
                $user->id
            )
            ->where(
                'status',
                Agent::STATUS_ACTIVE
            )
            ->first();
    }

    private function relations(): array
    {
        return [
            'season:id,code,name,status',

            'agentCollection:id,collection_code,collection_date,status',

            'agent.user:id,name,email,phone',

            'collectionPoint:id,name',

            'balanceOfficer:id,name,email,phone',

            'creator:id,name',

            'updater:id,name',

            'confirmer:id,name',

            'canceller:id,name',
        ];
    }

    private function data(
        FieldWeighing $weighing
    ): array {
        return [
            'id' =>
                $weighing->id,

            'weighing_code' =>
                $weighing->weighing_code,

            'coffee_season_id' =>
                $weighing
                    ->coffee_season_id,

            'agent_collection_id' =>
                $weighing
                    ->agent_collection_id,

            'agent_id' =>
                $weighing->agent_id,

            'collection_point_id' =>
                $weighing
                    ->collection_point_id,

            'balance_officer_id' =>
                $weighing
                    ->balance_officer_id,

            'expected_quantity_kg' =>
                $this->decimal(
                    $weighing
                        ->expected_quantity_kg
                ),

            'field_weight_kg' =>
                $this->decimal(
                    $weighing
                        ->field_weight_kg
                ),

            'difference_kg' =>
                $this->decimal(
                    $weighing
                        ->difference_kg
                ),

            'difference_percentage' =>
                number_format(
                    (float)
                    $weighing
                        ->difference_percentage,
                    4,
                    '.',
                    ''
                ),

            'bag_count' =>
                $weighing->bag_count,

            'weighed_at' =>
                $weighing->weighed_at
                    ?->toISOString(),

            'status' =>
                $weighing->status,

            'notes' =>
                $weighing->notes,

            'season' =>
                $weighing->season,

            'agent_collection' =>
                $weighing
                    ->agentCollection,

            'agent' =>
                $weighing->agent,

            'collection_point' =>
                $weighing
                    ->collectionPoint,

            'balance_officer' =>
                $weighing
                    ->balanceOfficer,

            'creator' =>
                $weighing->creator,

            'updater' =>
                $weighing->updater,

            'confirmer' =>
                $weighing->confirmer,

            'canceller' =>
                $weighing->canceller,

            'confirmed_at' =>
                $weighing
                    ->confirmed_at,

            'cancelled_at' =>
                $weighing
                    ->cancelled_at,

            'cancellation_reason' =>
                $weighing
                    ->cancellation_reason,
        ];
    }

    private function decimal(
        $value
    ): string {
        return number_format(
            (float) $value,
            2,
            '.',
            ''
        );
    }
}
