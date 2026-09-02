<?php

namespace App\Http\Controllers\API\FactoryReception;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\FactoryReception\CancelFactoryReceptionRequest;
use App\Http\Requests\API\FactoryReception\StoreFactoryReceptionRequest;
use App\Http\Requests\API\FactoryReception\UpdateFactoryReceptionRequest;
use App\Models\Agent;
use App\Models\FactoryReception;
use App\Models\FieldWeighing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FactoryReceptionController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view factory receptions.',
                [],
                403
            );
        }

        $query = FactoryReception::query()
            ->with($this->relations());

        $this->restrictScope($query, $user);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where(
                        'reception_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'fieldWeighing',
                        fn (Builder $weighing) =>
                            $weighing->where(
                                'weighing_code',
                                'like',
                                "%{$search}%"
                            )
                    )
                    ->orWhereHas(
                        'agentCollection',
                        fn (Builder $collection) =>
                            $collection->where(
                                'collection_code',
                                'like',
                                "%{$search}%"
                            )
                    );
            });
        }

        foreach ([
            'status',
            'agent_id',
            'coffee_season_id',
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
                'received_at',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'received_at',
                '<=',
                $request->date_to
            );
        }

        $items = $query
            ->latest('id')
            ->paginate(
                min(
                    max(
                        (int) $request->get('per_page', 20),
                        1
                    ),
                    100
                )
            );

        return $this->sendResponse([
            'items' => collect($items->items())
                ->map(
                    fn ($item) =>
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
        ], 'Factory receptions retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view factory reception summary.',
                [],
                403
            );
        }

        $query = FactoryReception::query();

        $this->restrictScope($query, $user);

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
                FactoryReception::STATUS_CONFIRMED
            );

        $difference = (float)
            (clone $confirmed)
                ->sum('difference_kg');

        return $this->sendResponse([
            'total_records' =>
                (clone $query)->count(),

            'draft_records' =>
                (clone $query)
                    ->where(
                        'status',
                        FactoryReception::STATUS_DRAFT
                    )
                    ->count(),

            'confirmed_records' =>
                (clone $query)
                    ->where(
                        'status',
                        FactoryReception::STATUS_CONFIRMED
                    )
                    ->count(),

            'cancelled_records' =>
                (clone $query)
                    ->where(
                        'status',
                        FactoryReception::STATUS_CANCELLED
                    )
                    ->count(),

            'field_weight_kg' =>
                $this->decimal(
                    (clone $confirmed)
                        ->sum('field_weight_kg')
                ),

            'factory_weight_kg' =>
                $this->decimal(
                    (clone $confirmed)
                        ->sum('factory_weight_kg')
                ),

            'difference_kg' =>
                $this->decimal($difference),

            'shortage_quantity_kg' =>
                $this->decimal(
                    $difference < 0
                        ? abs($difference)
                        : 0
                ),
        ], 'Factory reception summary retrieved successfully.');
    }

    public function eligibleWeighings(
        Request $request
    ): JsonResponse {
        if ($request->user()->role !== 'balance') {
            return $this->sendError(
                'Only Balance Officers can access weighings awaiting factory reception.',
                [],
                403
            );
        }

        $items = FieldWeighing::query()
            ->with([
                'agentCollection:id,collection_code,collection_date,status',
                'agent.user:id,name,email,phone',
                'collectionPoint:id,name',
            ])
            ->where(
                'status',
                FieldWeighing::STATUS_CONFIRMED
            )
            ->whereDoesntHave(
                'factoryReceptions',
                fn (Builder $query) =>
                    $query->where(
                        'status',
                        '!=',
                        FactoryReception::STATUS_CANCELLED
                    )
            )
            ->latest('id')
            ->limit(100)
            ->get();

        return $this->sendResponse([
            'items' => $items,
        ], 'Eligible field weighings retrieved successfully.');
    }

    public function store(
        StoreFactoryReceptionRequest $request
    ): JsonResponse {
        $weighing = FieldWeighing::query()
            ->with([
                'agentCollection',
                'agent',
                'collectionPoint',
            ])
            ->find(
                $request->integer(
                    'field_weighing_id'
                )
            );

        if (
            !$weighing ||
            $weighing->status !==
                FieldWeighing::STATUS_CONFIRMED
        ) {
            return $this->sendError(
                'A confirmed field weighing is required.',
                [],
                422
            );
        }

        $exists = FactoryReception::query()
            ->where(
                'field_weighing_id',
                $weighing->id
            )
            ->where(
                'status',
                '!=',
                FactoryReception::STATUS_CANCELLED
            )
            ->exists();

        if ($exists) {
            return $this->sendError(
                'This field weighing already has an active factory reception.',
                [],
                422
            );
        }

        if (
            Carbon::parse(
                $request->received_at
            )->lt($weighing->weighed_at)
        ) {
            return $this->sendError(
                'Factory reception cannot happen before field weighing.',
                [],
                422
            );
        }

        $fieldWeight = (float)
            $weighing->field_weight_kg;

        $factoryWeight = (float)
            $request->factory_weight_kg;

        [$difference, $percentage] =
            $this->difference(
                $fieldWeight,
                $factoryWeight
            );

        $reception = FactoryReception::create([
            'coffee_season_id' =>
                $weighing->coffee_season_id,

            'agent_collection_id' =>
                $weighing->agent_collection_id,

            'field_weighing_id' =>
                $weighing->id,

            'agent_id' =>
                $weighing->agent_id,

            'collection_point_id' =>
                $weighing->collection_point_id,

            'balance_officer_id' =>
                $request->user()->id,

            'field_weight_kg' =>
                $fieldWeight,

            'factory_weight_kg' =>
                $factoryWeight,

            'difference_kg' =>
                $difference,

            'difference_percentage' =>
                $percentage,

            'bag_count' =>
                $request->integer(
                    'bag_count'
                ) ?: null,

            'received_at' =>
                $request->received_at,

            'status' =>
                FactoryReception::STATUS_DRAFT,

            'notes' =>
                $request->notes,

            'created_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $reception->fresh()
                    ->load($this->relations())
            ),
            'Factory reception created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        FactoryReception $factoryReception
    ): JsonResponse {
        if (
            !$this->canAccess(
                $request->user(),
                $factoryReception
            )
        ) {
            return $this->sendError(
                'You are not allowed to view this factory reception.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $factoryReception->load(
                    $this->relations()
                )
            ),
            'Factory reception retrieved successfully.'
        );
    }

    public function update(
        UpdateFactoryReceptionRequest $request,
        FactoryReception $factoryReception
    ): JsonResponse {
        if (
            !$this->owns(
                $request->user(),
                $factoryReception
            )
        ) {
            return $this->sendError(
                'You can only update your own factory reception.',
                [],
                403
            );
        }

        if (
            $factoryReception->status !==
            FactoryReception::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft factory receptions can be edited.',
                [],
                422
            );
        }

        if (
            Carbon::parse(
                $request->received_at
            )->lt(
                $factoryReception
                    ->fieldWeighing
                    ->weighed_at
            )
        ) {
            return $this->sendError(
                'Factory reception cannot happen before field weighing.',
                [],
                422
            );
        }

        $fieldWeight = (float)
            $factoryReception
                ->field_weight_kg;

        $factoryWeight = (float)
            $request->factory_weight_kg;

        [$difference, $percentage] =
            $this->difference(
                $fieldWeight,
                $factoryWeight
            );

        $factoryReception->update([
            'factory_weight_kg' =>
                $factoryWeight,

            'difference_kg' =>
                $difference,

            'difference_percentage' =>
                $percentage,

            'bag_count' =>
                $request->integer(
                    'bag_count'
                ) ?: null,

            'received_at' =>
                $request->received_at,

            'notes' =>
                $request->notes,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $factoryReception->fresh()
                    ->load($this->relations())
            ),
            'Factory reception updated successfully.'
        );
    }

    public function confirm(
        Request $request,
        FactoryReception $factoryReception
    ): JsonResponse {
        if (
            !$this->owns(
                $request->user(),
                $factoryReception
            )
        ) {
            return $this->sendError(
                'You can only confirm your own factory reception.',
                [],
                403
            );
        }

        if (
            $factoryReception->status !==
            FactoryReception::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft factory receptions can be confirmed.',
                [],
                422
            );
        }

        $factoryReception->update([
            'status' =>
                FactoryReception::STATUS_CONFIRMED,

            'confirmed_by' =>
                $request->user()->id,

            'confirmed_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $factoryReception->fresh()
                    ->load($this->relations())
            ),
            'Factory reception confirmed successfully.'
        );
    }

    public function cancel(
        CancelFactoryReceptionRequest $request,
        FactoryReception $factoryReception
    ): JsonResponse {
        if (
            !$this->owns(
                $request->user(),
                $factoryReception
            )
        ) {
            return $this->sendError(
                'You can only cancel your own factory reception.',
                [],
                403
            );
        }

        if (
            $factoryReception->status ===
            FactoryReception::STATUS_CANCELLED
        ) {
            return $this->sendError(
                'This factory reception is already cancelled.',
                [],
                422
            );
        }

        $factoryReception->update([
            'status' =>
                FactoryReception::STATUS_CANCELLED,

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
                $factoryReception->fresh()
                    ->load($this->relations())
            ),
            'Factory reception cancelled successfully.'
        );
    }

    private function difference(
        float $field,
        float $factory
    ): array {
        $difference =
            round(
                $factory - $field,
                2
            );

        $percentage =
            $field > 0
                ? round(
                    ($difference / $field) * 100,
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
        FactoryReception $reception
    ): bool {
        if (
            in_array(
                $user->role,
                ['admin', 'accountant'],
                true
            )
        ) {
            return true;
        }

        if ($user->role === 'balance') {
            return
                $reception->balance_officer_id ===
                $user->id;
        }

        if ($user->role === 'agent') {
            $agent =
                $this->agentForUser($user);

            return $agent &&
                $agent->id ===
                    $reception->agent_id;
        }

        return false;
    }

    private function owns(
        User $user,
        FactoryReception $reception
    ): bool {
        return
            $user->role === 'balance' &&
            $reception->balance_officer_id ===
                $user->id;
    }

    private function restrictScope(
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
            $query->where(
                'balance_officer_id',
                $user->id
            );

            return;
        }

        if ($user->role === 'agent') {
            $agent =
                $this->agentForUser($user);

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
            'fieldWeighing:id,weighing_code,field_weight_kg,weighed_at,status',
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
        FactoryReception $reception
    ): array {
        return [
            'id' => $reception->id,

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

            'balance_officer_id' =>
                $reception->balance_officer_id,

            'field_weight_kg' =>
                $this->decimal(
                    $reception->field_weight_kg
                ),

            'factory_weight_kg' =>
                $this->decimal(
                    $reception->factory_weight_kg
                ),

            'difference_kg' =>
                $this->decimal(
                    $reception->difference_kg
                ),

            'difference_percentage' =>
                number_format(
                    (float)
                    $reception
                        ->difference_percentage,
                    4,
                    '.',
                    ''
                ),

            'bag_count' =>
                $reception->bag_count,

            'received_at' =>
                $reception->received_at
                    ?->toISOString(),

            'status' =>
                $reception->status,

            'notes' =>
                $reception->notes,

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

            'balance_officer' =>
                $reception->balanceOfficer,

            'creator' =>
                $reception->creator,

            'confirmer' =>
                $reception->confirmer,

            'canceller' =>
                $reception->canceller,

            'confirmed_at' =>
                $reception->confirmed_at,

            'cancelled_at' =>
                $reception->cancelled_at,

            'cancellation_reason' =>
                $reception
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
