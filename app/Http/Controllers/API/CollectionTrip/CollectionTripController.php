<?php

namespace App\Http\Controllers\API\CollectionTrip;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\CollectionTrip\CancelCollectionTripRequest;
use App\Http\Requests\API\CollectionTrip\StoreCollectionTripRequest;
use App\Http\Requests\API\CollectionTrip\UpdateCollectionTripRequest;
use App\Models\Agent;
use App\Models\CollectionTrip;
use App\Models\FieldWeighing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollectionTripController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view collection trips.',
                [],
                403
            );
        }

        $query = CollectionTrip::query()
            ->with($this->relations());

        $this->restrictScope(
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
                        'trip_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'vehicle_registration',
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
                        'fieldWeighing',
                        fn (Builder $weighing) =>
                            $weighing->where(
                                'weighing_code',
                                'like',
                                "%{$search}%"
                            )
                    )
                    ->orWhereHas(
                        'driver',
                        fn (Builder $driver) =>
                            $driver->where(
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
            'driver_user_id',
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
                    fn (CollectionTrip $trip) =>
                        $this->data($trip)
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
        ], 'Collection trips retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view collection trip summary.',
                [],
                403
            );
        }

        $query = CollectionTrip::query();

        $this->restrictScope(
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

        return $this->sendResponse([
            'total_trips' =>
                (clone $query)->count(),

            'planned_trips' =>
                (clone $query)
                    ->where(
                        'status',
                        CollectionTrip::STATUS_PLANNED
                    )
                    ->count(),

            'in_transit_trips' =>
                (clone $query)
                    ->where(
                        'status',
                        CollectionTrip::STATUS_IN_TRANSIT
                    )
                    ->count(),

            'arrived_trips' =>
                (clone $query)
                    ->where(
                        'status',
                        CollectionTrip::STATUS_ARRIVED
                    )
                    ->count(),

            'completed_trips' =>
                (clone $query)
                    ->where(
                        'status',
                        CollectionTrip::STATUS_COMPLETED
                    )
                    ->count(),

            'cancelled_trips' =>
                (clone $query)
                    ->where(
                        'status',
                        CollectionTrip::STATUS_CANCELLED
                    )
                    ->count(),

            'completed_weight_kg' =>
                $this->decimal(
                    (clone $query)
                        ->where(
                            'status',
                            CollectionTrip::STATUS_COMPLETED
                        )
                        ->sum(
                            'field_weight_kg'
                        )
                ),
        ], 'Collection trip summary retrieved successfully.');
    }

    public function eligibleWeighings(
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
                'You are not allowed to create collection trips.',
                [],
                403
            );
        }

        $items = FieldWeighing::query()
            ->with([
                'season:id,code,name,status',
                'agentCollection:id,collection_code,collection_date,status',
                'agent.user:id,name,email,phone',
                'collectionPoint:id,name',
            ])
            ->where(
                'status',
                FieldWeighing::STATUS_CONFIRMED
            )
            ->whereDoesntHave(
                'collectionTrips',
                fn (Builder $query) =>
                    $query->where(
                        'status',
                        '!=',
                        CollectionTrip::STATUS_CANCELLED
                    )
            )
            ->latest('id')
            ->limit(100)
            ->get();

        return $this->sendResponse([
            'items' => $items
                ->map(fn (FieldWeighing $weighing) => [
                    'id' =>
                        $weighing->id,

                    'weighing_code' =>
                        $weighing->weighing_code,

                    'coffee_season_id' =>
                        $weighing->coffee_season_id,

                    'agent_collection_id' =>
                        $weighing->agent_collection_id,

                    'agent_id' =>
                        $weighing->agent_id,

                    'collection_point_id' =>
                        $weighing->collection_point_id,

                    'field_weight_kg' =>
                        $this->decimal(
                            $weighing->field_weight_kg
                        ),

                    'weighed_at' =>
                        $weighing->weighed_at
                            ?->toISOString(),

                    'season' =>
                        $weighing->season,

                    'agent_collection' =>
                        $weighing->agentCollection,

                    'agent' =>
                        $weighing->agent,

                    'collection_point' =>
                        $weighing->collectionPoint,
                ])
                ->values(),
        ], 'Eligible field weighings retrieved successfully.');
    }

    public function driverLookup(
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
                'You are not allowed to access drivers.',
                [],
                403
            );
        }

        $drivers = User::query()
            ->select([
                'id',
                'name',
                'email',
                'phone',
            ])
            ->where(
                'role',
                'driver'
            )
            ->where(
                'status',
                'active'
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('name')
            ->get();

        return $this->sendResponse([
            'items' => $drivers,
        ], 'Drivers retrieved successfully.');
    }

    public function store(
        StoreCollectionTripRequest $request
    ): JsonResponse {
        $weighing = FieldWeighing::query()
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

        if (
            $request->filled(
                'driver_user_id'
            ) &&
            !$this->validDriver(
                $request->integer(
                    'driver_user_id'
                )
            )
        ) {
            return $this->sendError(
                'The selected user is not an active Driver.',
                [],
                422
            );
        }

        $trip = DB::transaction(function () use (
            $request,
            $weighing
        ) {
            $exists = CollectionTrip::query()
                ->where(
                    'field_weighing_id',
                    $weighing->id
                )
                ->where(
                    'status',
                    '!=',
                    CollectionTrip::STATUS_CANCELLED
                )
                ->exists();

            if ($exists) {
                return null;
            }

            return CollectionTrip::create([
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

                'driver_user_id' =>
                    $request->integer(
                        'driver_user_id'
                    ) ?: null,

                'vehicle_registration' =>
                    $request->vehicle_registration
                        ? trim(
                            $request->vehicle_registration
                        )
                        : null,

                'field_weight_kg' =>
                    $weighing->field_weight_kg,

                'status' =>
                    CollectionTrip::STATUS_PLANNED,

                'notes' =>
                    $request->notes,

                'created_by' =>
                    $request->user()->id,
            ]);
        });

        if (!$trip) {
            return $this->sendError(
                'This field weighing already has an active collection trip.',
                [],
                422
            );
        }

        return $this->sendResponse(
            $this->data(
                $trip->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Collection trip created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        CollectionTrip $collectionTrip
    ): JsonResponse {
        if (
            !$this->canAccess(
                $request->user(),
                $collectionTrip
            )
        ) {
            return $this->sendError(
                'You are not allowed to view this collection trip.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $collectionTrip->load(
                    $this->relations()
                )
            ),
            'Collection trip retrieved successfully.'
        );
    }

    public function update(
        UpdateCollectionTripRequest $request,
        CollectionTrip $collectionTrip
    ): JsonResponse {
        if (
            $collectionTrip->status !==
            CollectionTrip::STATUS_PLANNED
        ) {
            return $this->sendError(
                'Only planned collection trips can be edited.',
                [],
                422
            );
        }

        if (
            $request->filled(
                'driver_user_id'
            ) &&
            !$this->validDriver(
                $request->integer(
                    'driver_user_id'
                )
            )
        ) {
            return $this->sendError(
                'The selected user is not an active Driver.',
                [],
                422
            );
        }

        $collectionTrip->update([
            'driver_user_id' =>
                $request->integer(
                    'driver_user_id'
                ) ?: null,

            'vehicle_registration' =>
                $request->vehicle_registration
                    ? trim(
                        $request->vehicle_registration
                    )
                    : null,

            'notes' =>
                $request->notes,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $collectionTrip->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Collection trip updated successfully.'
        );
    }

    public function start(
        Request $request,
        CollectionTrip $collectionTrip
    ): JsonResponse {
        if (
            $request->user()->role !==
            'driver'
        ) {
            return $this->sendError(
                'Only the assigned Driver can start this trip.',
                [],
                403
            );
        }

        if (
            $collectionTrip->driver_user_id !==
            $request->user()->id
        ) {
            return $this->sendError(
                'This trip is assigned to another Driver.',
                [],
                403
            );
        }

        if (
            $collectionTrip->status !==
            CollectionTrip::STATUS_PLANNED
        ) {
            return $this->sendError(
                'Only planned trips can be started.',
                [],
                422
            );
        }

        $collectionTrip->update([
            'status' =>
                CollectionTrip::STATUS_IN_TRANSIT,

            'departure_at' =>
                now(),

            'started_by' =>
                $request->user()->id,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $collectionTrip->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Collection trip started successfully.'
        );
    }

    public function arrive(
        Request $request,
        CollectionTrip $collectionTrip
    ): JsonResponse {
        if (
            $request->user()->role !==
            'driver'
        ) {
            return $this->sendError(
                'Only the assigned Driver can mark this trip as arrived.',
                [],
                403
            );
        }

        if (
            $collectionTrip->driver_user_id !==
            $request->user()->id
        ) {
            return $this->sendError(
                'This trip is assigned to another Driver.',
                [],
                403
            );
        }

        if (
            $collectionTrip->status !==
            CollectionTrip::STATUS_IN_TRANSIT
        ) {
            return $this->sendError(
                'Only trips in transit can be marked as arrived.',
                [],
                422
            );
        }

        $collectionTrip->update([
            'status' =>
                CollectionTrip::STATUS_ARRIVED,

            'arrived_at' =>
                now(),

            'arrived_by' =>
                $request->user()->id,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $collectionTrip->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Collection trip arrived successfully.'
        );
    }

    public function complete(
        Request $request,
        CollectionTrip $collectionTrip
    ): JsonResponse {
        if (
            !in_array(
                $request->user()->role,
                ['admin', 'balance'],
                true
            )
        ) {
            return $this->sendError(
                'You are not allowed to complete collection trips.',
                [],
                403
            );
        }

        if (
            $collectionTrip->status !==
            CollectionTrip::STATUS_ARRIVED
        ) {
            return $this->sendError(
                'Only arrived trips can be completed.',
                [],
                422
            );
        }

        $collectionTrip->update([
            'status' =>
                CollectionTrip::STATUS_COMPLETED,

            'completed_by' =>
                $request->user()->id,

            'completed_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $collectionTrip->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Collection trip completed successfully.'
        );
    }

    public function cancel(
        CancelCollectionTripRequest $request,
        CollectionTrip $collectionTrip
    ): JsonResponse {
        if (
            $collectionTrip->status !==
            CollectionTrip::STATUS_PLANNED
        ) {
            return $this->sendError(
                'Only planned trips can be cancelled.',
                [],
                422
            );
        }

        $collectionTrip->update([
            'status' =>
                CollectionTrip::STATUS_CANCELLED,

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
                $collectionTrip->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Collection trip cancelled successfully.'
        );
    }

    private function validDriver(
        int $userId
    ): bool {
        return User::query()
            ->where(
                'id',
                $userId
            )
            ->where(
                'role',
                'driver'
            )
            ->where(
                'status',
                'active'
            )
            ->where(
                'is_active',
                true
            )
            ->exists();
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
                    'driver',
                    'agent',
                ],
                true
            );
    }

    private function restrictScope(
        Builder $query,
        User $user
    ): void {
        if (
            in_array(
                $user->role,
                [
                    'admin',
                    'accountant',
                    'balance',
                ],
                true
            )
        ) {
            return;
        }

        if ($user->role === 'driver') {
            $query->where(
                'driver_user_id',
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

    private function canAccess(
        User $user,
        CollectionTrip $trip
    ): bool {
        if (
            in_array(
                $user->role,
                [
                    'admin',
                    'accountant',
                    'balance',
                ],
                true
            )
        ) {
            return true;
        }

        if ($user->role === 'driver') {
            return
                $trip->driver_user_id ===
                $user->id;
        }

        if ($user->role === 'agent') {
            $agent =
                $this->agentForUser(
                    $user
                );

            return $agent &&
                $trip->agent_id ===
                $agent->id;
        }

        return false;
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

            'driver:id,name,email,phone',

            'creator:id,name',

            'updater:id,name',

            'starter:id,name',

            'arriver:id,name',

            'completer:id,name',

            'canceller:id,name',
        ];
    }

    private function data(
        CollectionTrip $trip
    ): array {
        return [
            'id' =>
                $trip->id,

            'trip_code' =>
                $trip->trip_code,

            'coffee_season_id' =>
                $trip->coffee_season_id,

            'agent_collection_id' =>
                $trip->agent_collection_id,

            'field_weighing_id' =>
                $trip->field_weighing_id,

            'agent_id' =>
                $trip->agent_id,

            'collection_point_id' =>
                $trip->collection_point_id,

            'driver_user_id' =>
                $trip->driver_user_id,

            'vehicle_registration' =>
                $trip->vehicle_registration,

            'field_weight_kg' =>
                $this->decimal(
                    $trip->field_weight_kg
                ),

            'status' =>
                $trip->status,

            'departure_at' =>
                $trip->departure_at
                    ?->toISOString(),

            'arrived_at' =>
                $trip->arrived_at
                    ?->toISOString(),

            'notes' =>
                $trip->notes,

            'season' =>
                $trip->season,

            'agent_collection' =>
                $trip->agentCollection,

            'field_weighing' =>
                $trip->fieldWeighing,

            'agent' =>
                $trip->agent,

            'collection_point' =>
                $trip->collectionPoint,

            'driver' =>
                $trip->driver,

            'creator' =>
                $trip->creator,

            'starter' =>
                $trip->starter,

            'arriver' =>
                $trip->arriver,

            'completer' =>
                $trip->completer,

            'canceller' =>
                $trip->canceller,

            'completed_at' =>
                $trip->completed_at,

            'cancelled_at' =>
                $trip->cancelled_at,

            'cancellation_reason' =>
                $trip->cancellation_reason,
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
