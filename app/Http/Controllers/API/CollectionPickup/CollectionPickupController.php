<?php

namespace App\Http\Controllers\API\CollectionPickup;

use App\Http\Controllers\API\BaseController;
use App\Models\Agent;
use App\Models\AgentCollection;
use App\Models\CoffeePurchase;
use App\Models\CollectionPickup;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CollectionPickupController extends BaseController
{
    public function drivers(Request $request): JsonResponse
    {
        if ($request->user()->role !== User::ROLE_AGENT) {
            return $this->sendError(
                'Only Agents can select a Driver for collection pickup.',
                [],
                403
            );
        }

        $drivers = Driver::query()
            ->with([
                'user:id,name,email,phone,role,status,is_active',
                'assignedVehicle:id,vehicle_code,registration_number,vehicle_type,capacity_kg,assigned_driver_id,status',
            ])
            ->where(
                'status',
                Driver::STATUS_ACTIVE
            )
            ->whereHas(
                'user',
                function ($query) {
                    $query
                        ->where(
                            'role',
                            User::ROLE_DRIVER
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->where(
                            'is_active',
                            true
                        );
                }
            )
            ->orderBy('driver_code')
            ->get();

        return $this->sendResponse([
            'items' => $drivers
                ->map(function (Driver $driver) {
                    return [
                        'id' => $driver->id,
                        'driver_code' => $driver->driver_code,
                        'status' => $driver->status,

                        'user' => $driver->user
                            ? [
                                'id' => $driver->user->id,
                                'name' => $driver->user->name,
                                'email' => $driver->user->email,
                                'phone' => $driver->user->phone,
                            ]
                            : null,

                        'vehicle' => $driver->assignedVehicle
                            ? [
                                'id' =>
                                    $driver->assignedVehicle->id,

                                'vehicle_code' =>
                                    $driver->assignedVehicle
                                        ->vehicle_code,

                                'registration_number' =>
                                    $driver->assignedVehicle
                                        ->registration_number,

                                'vehicle_type' =>
                                    $driver->assignedVehicle
                                        ->vehicle_type,

                                'capacity_kg' =>
                                    $driver->assignedVehicle
                                        ->capacity_kg,

                                'status' =>
                                    $driver->assignedVehicle
                                        ->status,
                            ]
                            : null,
                    ];
                })
                ->values(),
        ], 'Available Drivers retrieved successfully.');
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'agent_collection_id' => [
                'nullable',
                'integer',
                'exists:agent_collections,id',
            ],

            'status' => [
                'nullable',
                Rule::in(CollectionPickup::STATUSES),
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $user = $request->user();

        $query = CollectionPickup::query()
            ->with($this->relations());

        if ($user->role === User::ROLE_AGENT) {
            $agent = $this->agentForUser($user);

            if (!$agent) {
                return $this->sendError(
                    'Agent profile not found.',
                    [],
                    404
                );
            }

            $query->where(
                'agent_id',
                $agent->id
            );
        } elseif ($user->role === User::ROLE_DRIVER) {
            $driver = $this->driverForUser($user);

            if (!$driver) {
                return $this->sendError(
                    'Driver profile not found.',
                    [],
                    404
                );
            }

            $query->where(
                'driver_id',
                $driver->id
            );
        } elseif (
            !in_array(
                $user->role,
                [
                    User::ROLE_ADMIN,
                    User::ROLE_ACCOUNTANT,
                    User::ROLE_BALANCE,
                ],
                true
            )
        ) {
            return $this->sendError(
                'You are not allowed to access collection pickups.',
                [],
                403
            );
        }

        if ($request->filled('agent_collection_id')) {
            $query->where(
                'agent_collection_id',
                $request->integer(
                    'agent_collection_id'
                )
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')
                    ->toString()
            );
        }

        $items = $query
            ->orderByDesc('id')
            ->paginate(
                $request->integer('per_page', 50)
            );

        return $this->sendResponse([
            'items' => $items->getCollection()
                ->map(
                    fn (CollectionPickup $pickup) =>
                        $this->data($pickup)
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
        ], 'Collection pickups retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== User::ROLE_AGENT) {
            return $this->sendError(
                'Only an Agent can request collection pickup.',
                [],
                403
            );
        }

        $validated = $request->validate([
            'agent_collection_id' => [
                'required',
                'integer',
                'exists:agent_collections,id',
            ],

            'driver_id' => [
                'required',
                'integer',
                'exists:drivers,id',
            ],

            'request_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $agent = $this->agentForUser(
            $request->user()
        );

        if (!$agent) {
            return $this->sendError(
                'Active Agent profile not found.',
                [],
                404
            );
        }

        $collection = AgentCollection::query()
            ->whereKey(
                $validated['agent_collection_id']
            )
            ->where(
                'agent_id',
                $agent->id
            )
            ->first();

        if (!$collection) {
            return $this->sendError(
                'This collection does not belong to the logged-in Agent.',
                [],
                403
            );
        }

        if ($collection->status !== 'completed') {
            return $this->sendError(
                'Complete the collection before requesting a Driver.',
                [],
                422
            );
        }

        $driver = Driver::query()
            ->with([
                'user:id,name,email,phone,role,status,is_active',
                'assignedVehicle:id,vehicle_code,registration_number,vehicle_type,capacity_kg,assigned_driver_id,status',
            ])
            ->whereKey(
                $validated['driver_id']
            )
            ->where(
                'status',
                Driver::STATUS_ACTIVE
            )
            ->whereHas(
                'user',
                function ($query) {
                    $query
                        ->where(
                            'role',
                            User::ROLE_DRIVER
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->where(
                            'is_active',
                            true
                        );
                }
            )
            ->first();

        if (!$driver) {
            return $this->sendError(
                'The selected Driver is not available.',
                [],
                422
            );
        }

        $declaredQuantity = (float)
            CoffeePurchase::query()
                ->where(
                    'agent_collection_id',
                    $collection->id
                )
                ->where(
                    'status',
                    'approved'
                )
                ->sum('quantity_kg');

        if ($declaredQuantity <= 0) {
            return $this->sendError(
                'The completed collection does not contain an approved coffee quantity.',
                [],
                422
            );
        }

        return DB::transaction(function () use (
            $request,
            $validated,
            $agent,
            $collection,
            $driver,
            $declaredQuantity
        ) {
            AgentCollection::query()
                ->whereKey($collection->id)
                ->lockForUpdate()
                ->first();

            $existing = CollectionPickup::query()
                ->where(
                    'agent_collection_id',
                    $collection->id
                )
                ->whereNotIn(
                    'status',
                    [
                        CollectionPickup::STATUS_REJECTED,
                        CollectionPickup::STATUS_CANCELLED,
                    ]
                )
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $this->sendError(
                    'This collection already has an active pickup request.',
                    [],
                    422
                );
            }

            $pickup = CollectionPickup::create([
                'pickup_code' =>
                    'TMP-' .
                    Str::uuid()->toString(),

                'agent_collection_id' =>
                    $collection->id,

                'agent_id' =>
                    $agent->id,

                'driver_id' =>
                    $driver->id,

                'vehicle_registration' =>
                    $driver->assignedVehicle
                        ?->registration_number,

                'declared_quantity_kg' =>
                    round(
                        $declaredQuantity,
                        2
                    ),

                'status' =>
                    CollectionPickup::STATUS_PENDING,

                'request_note' =>
                    $validated['request_note']
                    ?? null,

                'requested_by' =>
                    $request->user()->id,

                'requested_at' =>
                    now(),
            ]);

            $pickup->update([
                'pickup_code' =>
                    sprintf(
                        'PKP-%s-%06d',
                        now()->format('Y'),
                        $pickup->id
                    ),
            ]);

            return $this->sendResponse(
                $this->data(
                    $pickup->fresh()
                        ->load(
                            $this->relations()
                        )
                ),
                'Pickup request sent to the Driver.',
                201
            );
        });
    }

    public function show(
        Request $request,
        CollectionPickup $collectionPickup
    ): JsonResponse {
        if (
            !$this->canAccess(
                $request->user(),
                $collectionPickup
            )
        ) {
            return $this->sendError(
                'You are not allowed to view this pickup.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $collectionPickup
                    ->load(
                        $this->relations()
                    )
            ),
            'Collection pickup retrieved successfully.'
        );
    }

    public function accept(
        Request $request,
        CollectionPickup $collectionPickup
    ): JsonResponse {
        if (
            !$this->assignedDriver(
                $request->user(),
                $collectionPickup
            )
        ) {
            return $this->sendError(
                'Only the assigned Driver can accept this pickup.',
                [],
                403
            );
        }

        if (
            $collectionPickup->status !==
            CollectionPickup::STATUS_PENDING
        ) {
            return $this->sendError(
                'Only a pending pickup can be accepted.',
                [],
                422
            );
        }

        $validated = $request->validate([
            'response_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $collectionPickup->update([
            'status' =>
                CollectionPickup::STATUS_ACCEPTED,

            'response_note' =>
                $validated['response_note']
                ?? null,

            'accepted_by' =>
                $request->user()->id,

            'accepted_at' =>
                now(),
        ]);

        return $this->sendResponse(
            $this->data(
                $collectionPickup->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Pickup request accepted.'
        );
    }

    public function reject(
        Request $request,
        CollectionPickup $collectionPickup
    ): JsonResponse {
        if (
            !$this->assignedDriver(
                $request->user(),
                $collectionPickup
            )
        ) {
            return $this->sendError(
                'Only the assigned Driver can reject this pickup.',
                [],
                403
            );
        }

        if (
            !in_array(
                $collectionPickup->status,
                [
                    CollectionPickup::STATUS_PENDING,
                    CollectionPickup::STATUS_ACCEPTED,
                ],
                true
            )
        ) {
            return $this->sendError(
                'This pickup can no longer be rejected.',
                [],
                422
            );
        }

        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:1000',
            ],
        ]);

        $collectionPickup->update([
            'status' =>
                CollectionPickup::STATUS_REJECTED,

            'response_note' =>
                $validated['reason'],

            'rejected_by' =>
                $request->user()->id,

            'rejected_at' =>
                now(),
        ]);

        return $this->sendResponse(
            $this->data(
                $collectionPickup->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Pickup request rejected.'
        );
    }

    public function confirmWeight(
        Request $request,
        CollectionPickup $collectionPickup
    ): JsonResponse {
        if (
            !$this->assignedDriver(
                $request->user(),
                $collectionPickup
            )
        ) {
            return $this->sendError(
                'Only the assigned Driver can confirm pickup weight.',
                [],
                403
            );
        }

        if (
            $collectionPickup->status !==
            CollectionPickup::STATUS_ACCEPTED
        ) {
            return $this->sendError(
                'The Driver must accept the pickup before confirming weight.',
                [],
                422
            );
        }

        $validated = $request->validate([
            'pickup_weight_kg' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999.99',
            ],

            'weight_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $weight = round(
            (float) $validated['pickup_weight_kg'],
            2
        );

        $difference = round(
            $weight -
            (float) $collectionPickup
                ->declared_quantity_kg,
            2
        );

        $receiptCode = sprintf(
            'HND-%s-%06d',
            now()->format('Y'),
            $collectionPickup->id
        );

        $collectionPickup->update([
            'pickup_weight_kg' =>
                $weight,

            'pickup_difference_kg' =>
                $difference,

            'weight_note' =>
                $validated['weight_note']
                ?? null,

            'receipt_code' =>
                $receiptCode,

            'status' =>
                CollectionPickup::STATUS_PICKUP_CONFIRMED,

            'pickup_confirmed_by' =>
                $request->user()->id,

            'pickup_confirmed_at' =>
                now(),
        ]);

        return $this->sendResponse(
            $this->data(
                $collectionPickup->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Pickup weight confirmed. Handover receipt generated.'
        );
    }

    public function startTransport(
        Request $request,
        CollectionPickup $collectionPickup
    ): JsonResponse {
        if (
            !$this->assignedDriver(
                $request->user(),
                $collectionPickup
            )
        ) {
            return $this->sendError(
                'Only the assigned Driver can start transport.',
                [],
                403
            );
        }

        if (
            $collectionPickup->status !==
            CollectionPickup::STATUS_PICKUP_CONFIRMED
        ) {
            return $this->sendError(
                'Confirm the pickup weight before starting transport.',
                [],
                422
            );
        }

        $collectionPickup->update([
            'status' =>
                CollectionPickup::STATUS_IN_TRANSIT,

            'departed_at' =>
                now(),
        ]);

        return $this->sendResponse(
            $this->data(
                $collectionPickup->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Coffee transport started.'
        );
    }

    public function arriveFactory(
        Request $request,
        CollectionPickup $collectionPickup
    ): JsonResponse {
        if (
            !$this->assignedDriver(
                $request->user(),
                $collectionPickup
            )
        ) {
            return $this->sendError(
                'Only the assigned Driver can mark arrival.',
                [],
                403
            );
        }

        if (
            $collectionPickup->status !==
            CollectionPickup::STATUS_IN_TRANSIT
        ) {
            return $this->sendError(
                'Only coffee currently in transit can be marked as arrived.',
                [],
                422
            );
        }

        $collectionPickup->update([
            'status' =>
                CollectionPickup::STATUS_ARRIVED,

            'arrived_at' =>
                now(),
        ]);

        return $this->sendResponse(
            $this->data(
                $collectionPickup->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Coffee marked as arrived at Gihombo.'
        );
    }

    public function recordFactoryWeight(
        Request $request,
        CollectionPickup $collectionPickup
    ): JsonResponse {
        if (
            $request->user()->role !==
            User::ROLE_BALANCE
        ) {
            return $this->sendError(
                'Only the Balance Officer can record factory weight.',
                [],
                403
            );
        }

        if (
            $collectionPickup->status !==
            CollectionPickup::STATUS_ARRIVED
        ) {
            return $this->sendError(
                'The Driver must mark the coffee as arrived before factory weighing.',
                [],
                422
            );
        }

        $validated = $request->validate([
            'factory_weight_kg' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999.99',
            ],

            'factory_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $factoryWeight = round(
            (float)
            $validated['factory_weight_kg'],
            2
        );

        $difference = round(
            $factoryWeight -
            (float)
            $collectionPickup->pickup_weight_kg,
            2
        );

        $collectionPickup->update([
            'factory_weight_kg' =>
                $factoryWeight,

            'factory_difference_kg' =>
                $difference,

            'factory_note' =>
                $validated['factory_note']
                ?? null,

            'factory_weighed_by' =>
                $request->user()->id,

            'factory_weighed_at' =>
                now(),

            'status' =>
                CollectionPickup::STATUS_FACTORY_WEIGHED,
        ]);

        return $this->sendResponse(
            $this->data(
                $collectionPickup->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Factory weight recorded successfully.'
        );
    }

    public function acknowledgeFactoryWeight(
        Request $request,
        CollectionPickup $collectionPickup
    ): JsonResponse {
        if (
            !$this->assignedDriver(
                $request->user(),
                $collectionPickup
            )
        ) {
            return $this->sendError(
                'Only the assigned Driver can acknowledge the factory comparison.',
                [],
                403
            );
        }

        if (
            $collectionPickup->status !==
            CollectionPickup::STATUS_FACTORY_WEIGHED
        ) {
            return $this->sendError(
                'Factory weight must be recorded first.',
                [],
                422
            );
        }

        $collectionPickup->update([
            'status' =>
                CollectionPickup::STATUS_FACTORY_ACKNOWLEDGED,

            'factory_acknowledged_by' =>
                $request->user()->id,

            'factory_acknowledged_at' =>
                now(),
        ]);

        return $this->sendResponse(
            $this->data(
                $collectionPickup->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Driver factory weight acknowledgement recorded.'
        );
    }

    public function receipt(
        Request $request,
        CollectionPickup $collectionPickup
    ): JsonResponse {
        if (
            !$this->canAccess(
                $request->user(),
                $collectionPickup
            )
        ) {
            return $this->sendError(
                'You are not allowed to view this receipt.',
                [],
                403
            );
        }

        if (!$collectionPickup->receipt_code) {
            return $this->sendError(
                'The Driver has not confirmed pickup weight yet.',
                [],
                422
            );
        }

        $collectionPickup->load(
            $this->relations()
        );

        return $this->sendResponse([
            'receipt_code' =>
                $collectionPickup->receipt_code,

            'pickup_code' =>
                $collectionPickup->pickup_code,

            'collection_code' =>
                $collectionPickup->collection
                    ?->collection_code,

            'agent' =>
                $collectionPickup->agent?->user?->name,

            'agent_code' =>
                $collectionPickup->agent?->agent_code,

            'driver' =>
                $collectionPickup->driver?->user?->name,

            'driver_code' =>
                $collectionPickup->driver?->driver_code,

            'vehicle_registration' =>
                $collectionPickup->vehicle_registration,

            'declared_quantity_kg' =>
                $collectionPickup->declared_quantity_kg,

            'pickup_weight_kg' =>
                $collectionPickup->pickup_weight_kg,

            'pickup_difference_kg' =>
                $collectionPickup->pickup_difference_kg,

            'pickup_confirmed_at' =>
                $collectionPickup->pickup_confirmed_at,

            'factory_weight_kg' =>
                $collectionPickup->factory_weight_kg,

            'factory_difference_kg' =>
                $collectionPickup->factory_difference_kg,

            'status' =>
                $collectionPickup->status,
        ], 'Handover receipt retrieved successfully.');
    }

    public function cancel(
        Request $request,
        CollectionPickup $collectionPickup
    ): JsonResponse {
        $agent = $this->agentForUser(
            $request->user()
        );

        $allowed =
            $request->user()->role ===
                User::ROLE_ADMIN ||
            (
                $request->user()->role ===
                    User::ROLE_AGENT &&
                $agent &&
                $agent->id ===
                    $collectionPickup->agent_id
            );

        if (!$allowed) {
            return $this->sendError(
                'You are not allowed to cancel this pickup request.',
                [],
                403
            );
        }

        if (
            $collectionPickup->status !==
            CollectionPickup::STATUS_PENDING
        ) {
            return $this->sendError(
                'Only a pending pickup request can be cancelled.',
                [],
                422
            );
        }

        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:1000',
            ],
        ]);

        $collectionPickup->update([
            'status' =>
                CollectionPickup::STATUS_CANCELLED,

            'cancelled_by' =>
                $request->user()->id,

            'cancelled_at' =>
                now(),

            'cancellation_reason' =>
                $validated['reason'],
        ]);

        return $this->sendResponse(
            $this->data(
                $collectionPickup->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Pickup request cancelled.'
        );
    }

    private function agentForUser(
        User $user
    ): ?Agent {
        if (
            $user->role !==
            User::ROLE_AGENT
        ) {
            return null;
        }

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

    private function driverForUser(
        User $user
    ): ?Driver {
        if (
            $user->role !==
            User::ROLE_DRIVER
        ) {
            return null;
        }

        return Driver::query()
            ->where(
                'user_id',
                $user->id
            )
            ->first();
    }

    private function assignedDriver(
        User $user,
        CollectionPickup $pickup
    ): bool {
        $driver = $this->driverForUser(
            $user
        );

        return $driver &&
            $driver->id ===
                $pickup->driver_id;
    }

    private function canAccess(
        User $user,
        CollectionPickup $pickup
    ): bool {
        if (
            in_array(
                $user->role,
                [
                    User::ROLE_ADMIN,
                    User::ROLE_ACCOUNTANT,
                    User::ROLE_BALANCE,
                ],
                true
            )
        ) {
            return true;
        }

        if (
            $user->role ===
            User::ROLE_AGENT
        ) {
            $agent = $this->agentForUser(
                $user
            );

            return $agent &&
                $agent->id ===
                    $pickup->agent_id;
        }

        if (
            $user->role ===
            User::ROLE_DRIVER
        ) {
            return $this->assignedDriver(
                $user,
                $pickup
            );
        }

        return false;
    }

    private function relations(): array
    {
        return [
            'collection:id,collection_code,coffee_season_id,agent_id,collection_point_id,collection_date,status',

            'agent:id,agent_code,user_id,status',
            'agent.user:id,name,email,phone',

            'driver:id,driver_code,user_id,status',
            'driver.user:id,name,email,phone',

            'driver.assignedVehicle:id,vehicle_code,registration_number,vehicle_type,capacity_kg,assigned_driver_id,status',
        ];
    }

    private function data(
        CollectionPickup $pickup
    ): array {
        return [
            'id' =>
                $pickup->id,

            'pickup_code' =>
                $pickup->pickup_code,

            'receipt_code' =>
                $pickup->receipt_code,

            'agent_collection_id' =>
                $pickup->agent_collection_id,

            'agent_id' =>
                $pickup->agent_id,

            'driver_id' =>
                $pickup->driver_id,

            'vehicle_registration' =>
                $pickup->vehicle_registration,

            'declared_quantity_kg' =>
                $pickup->declared_quantity_kg,

            'pickup_weight_kg' =>
                $pickup->pickup_weight_kg,

            'pickup_difference_kg' =>
                $pickup->pickup_difference_kg,

            'factory_weight_kg' =>
                $pickup->factory_weight_kg,

            'factory_difference_kg' =>
                $pickup->factory_difference_kg,

            'status' =>
                $pickup->status,

            'request_note' =>
                $pickup->request_note,

            'response_note' =>
                $pickup->response_note,

            'weight_note' =>
                $pickup->weight_note,

            'factory_note' =>
                $pickup->factory_note,

            'requested_at' =>
                $pickup->requested_at,

            'accepted_at' =>
                $pickup->accepted_at,

            'rejected_at' =>
                $pickup->rejected_at,

            'pickup_confirmed_at' =>
                $pickup->pickup_confirmed_at,

            'departed_at' =>
                $pickup->departed_at,

            'arrived_at' =>
                $pickup->arrived_at,

            'factory_weighed_at' =>
                $pickup->factory_weighed_at,

            'factory_acknowledged_at' =>
                $pickup->factory_acknowledged_at,

            'cancelled_at' =>
                $pickup->cancelled_at,

            'cancellation_reason' =>
                $pickup->cancellation_reason,

            'collection' =>
                $pickup->collection
                    ? [
                        'id' =>
                            $pickup->collection->id,

                        'collection_code' =>
                            $pickup->collection
                                ->collection_code,

                        'collection_date' =>
                            $pickup->collection
                                ->collection_date,

                        'status' =>
                            $pickup->collection
                                ->status,
                    ]
                    : null,

            'agent' =>
                $pickup->agent
                    ? [
                        'id' =>
                            $pickup->agent->id,

                        'agent_code' =>
                            $pickup->agent
                                ->agent_code,

                        'name' =>
                            $pickup->agent
                                ->user?->name,

                        'phone' =>
                            $pickup->agent
                                ->user?->phone,
                    ]
                    : null,

            'driver' =>
                $pickup->driver
                    ? [
                        'id' =>
                            $pickup->driver->id,

                        'driver_code' =>
                            $pickup->driver
                                ->driver_code,

                        'name' =>
                            $pickup->driver
                                ->user?->name,

                        'phone' =>
                            $pickup->driver
                                ->user?->phone,

                        'vehicle' =>
                            $pickup->driver
                                ->assignedVehicle
                                ? [
                                    'id' =>
                                        $pickup->driver
                                            ->assignedVehicle
                                            ->id,

                                    'vehicle_code' =>
                                        $pickup->driver
                                            ->assignedVehicle
                                            ->vehicle_code,

                                    'registration_number' =>
                                        $pickup->driver
                                            ->assignedVehicle
                                            ->registration_number,
                                ]
                                : null,
                    ]
                    : null,

            'created_at' =>
                $pickup->created_at,

            'updated_at' =>
                $pickup->updated_at,
        ];
    }
}
