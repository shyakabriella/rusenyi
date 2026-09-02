<?php

namespace App\Http\Controllers\API\Transport;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Transport\AssignVehicleDriverRequest;
use App\Http\Requests\API\Transport\StoreVehicleRequest;
use App\Http\Requests\API\Transport\UpdateVehicleRequest;
use App\Http\Requests\API\Transport\UpdateVehicleStatusRequest;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view vehicles.',
                [],
                403
            );
        }

        $query = Vehicle::query()
            ->with($this->relations());

        if ($user->role === 'driver') {
            $driver = Driver::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

            $query->where(
                'assigned_driver_id',
                $driver?->id ?? 0
            );
        }

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->search
            );

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where(
                        'vehicle_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'registration_number',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'vehicle_type',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'make',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'model',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        if ($request->filled('vehicle_type')) {
            $query->where(
                'vehicle_type',
                $request->vehicle_type
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
            'items' =>
                collect(
                    $items->items()
                )
                    ->map(
                        fn (Vehicle $vehicle) =>
                            $this->data($vehicle)
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
        ], 'Vehicles retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        if (
            !in_array(
                $request->user()->role,
                ['admin', 'accountant', 'balance'],
                true
            )
        ) {
            return $this->sendError(
                'You are not allowed to view vehicle summary.',
                [],
                403
            );
        }

        return $this->sendResponse([
            'total_vehicles' =>
                Vehicle::count(),

            'available_vehicles' =>
                Vehicle::where(
                    'status',
                    Vehicle::STATUS_AVAILABLE
                )->count(),

            'assigned_vehicles' =>
                Vehicle::where(
                    'status',
                    Vehicle::STATUS_ASSIGNED
                )->count(),

            'in_trip_vehicles' =>
                Vehicle::where(
                    'status',
                    Vehicle::STATUS_IN_TRIP
                )->count(),

            'maintenance_vehicles' =>
                Vehicle::where(
                    'status',
                    Vehicle::STATUS_MAINTENANCE
                )->count(),

            'inactive_vehicles' =>
                Vehicle::where(
                    'status',
                    Vehicle::STATUS_INACTIVE
                )->count(),

            'total_capacity_kg' =>
                $this->decimal(
                    Vehicle::query()
                        ->whereNotIn(
                            'status',
                            [
                                Vehicle::STATUS_MAINTENANCE,
                                Vehicle::STATUS_INACTIVE,
                            ]
                        )
                        ->sum('capacity_kg')
                ),
        ], 'Vehicle summary retrieved successfully.');
    }

    public function lookup(Request $request): JsonResponse
    {
        if (
            !in_array(
                $request->user()->role,
                ['admin', 'balance'],
                true
            )
        ) {
            return $this->sendError(
                'You are not allowed to access vehicle lookup.',
                [],
                403
            );
        }

        $vehicles = Vehicle::query()
            ->with([
                'assignedDriver.user:id,name,email,phone',
            ])
            ->whereIn(
                'status',
                [
                    Vehicle::STATUS_AVAILABLE,
                    Vehicle::STATUS_ASSIGNED,
                ]
            )
            ->orderBy(
                'registration_number'
            )
            ->get();

        return $this->sendResponse([
            'items' =>
                $vehicles
                    ->map(
                        fn (Vehicle $vehicle) =>
                            $this->data($vehicle)
                    )
                    ->values(),
        ], 'Vehicle lookup retrieved successfully.');
    }

    public function myVehicle(
        Request $request
    ): JsonResponse {
        if ($request->user()->role !== 'driver') {
            return $this->sendError(
                'Only Drivers can access this vehicle.',
                [],
                403
            );
        }

        $driver = Driver::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->first();

        if (!$driver) {
            return $this->sendError(
                'Driver profile not found.',
                [],
                404
            );
        }

        $vehicle = Vehicle::query()
            ->with($this->relations())
            ->where(
                'assigned_driver_id',
                $driver->id
            )
            ->first();

        if (!$vehicle) {
            return $this->sendError(
                'No vehicle is currently assigned to this Driver.',
                [],
                404
            );
        }

        return $this->sendResponse(
            $this->data($vehicle),
            'Assigned vehicle retrieved successfully.'
        );
    }

    public function store(
        StoreVehicleRequest $request
    ): JsonResponse {
        $vehicle = Vehicle::create([
            'registration_number' =>
                strtoupper(
                    trim(
                        $request->registration_number
                    )
                ),

            'vehicle_type' =>
                trim(
                    $request->vehicle_type
                ),

            'make' =>
                $request->make
                    ? trim($request->make)
                    : null,

            'model' =>
                $request->model
                    ? trim($request->model)
                    : null,

            'manufacture_year' =>
                $request->manufacture_year,

            'capacity_kg' =>
                $request->capacity_kg,

            'status' =>
                Vehicle::STATUS_AVAILABLE,

            'notes' =>
                $request->notes,

            'created_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $vehicle->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Vehicle created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        Vehicle $vehicle
    ): JsonResponse {
        if (
            !$this->canAccess(
                $request->user(),
                $vehicle
            )
        ) {
            return $this->sendError(
                'You are not allowed to view this Vehicle.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $vehicle->load(
                    $this->relations()
                )
            ),
            'Vehicle retrieved successfully.'
        );
    }

    public function update(
        UpdateVehicleRequest $request,
        Vehicle $vehicle
    ): JsonResponse {
        if (
            $vehicle->status ===
            Vehicle::STATUS_IN_TRIP
        ) {
            return $this->sendError(
                'A vehicle currently in a trip cannot be edited.',
                [],
                422
            );
        }

        $vehicle->update([
            'registration_number' =>
                $request->has(
                    'registration_number'
                )
                    ? strtoupper(
                        trim(
                            $request->registration_number
                        )
                    )
                    : $vehicle->registration_number,

            'vehicle_type' =>
                $request->has('vehicle_type')
                    ? trim(
                        $request->vehicle_type
                    )
                    : $vehicle->vehicle_type,

            'make' =>
                $request->has('make')
                    ? (
                        $request->make
                            ? trim(
                                $request->make
                            )
                            : null
                    )
                    : $vehicle->make,

            'model' =>
                $request->has('model')
                    ? (
                        $request->model
                            ? trim(
                                $request->model
                            )
                            : null
                    )
                    : $vehicle->model,

            'manufacture_year' =>
                $request->has(
                    'manufacture_year'
                )
                    ? $request->manufacture_year
                    : $vehicle->manufacture_year,

            'capacity_kg' =>
                $request->has('capacity_kg')
                    ? $request->capacity_kg
                    : $vehicle->capacity_kg,

            'notes' =>
                $request->has('notes')
                    ? $request->notes
                    : $vehicle->notes,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $vehicle->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Vehicle updated successfully.'
        );
    }

    public function assignDriver(
        AssignVehicleDriverRequest $request,
        Vehicle $vehicle
    ): JsonResponse {
        if (
            in_array(
                $vehicle->status,
                [
                    Vehicle::STATUS_IN_TRIP,
                    Vehicle::STATUS_MAINTENANCE,
                    Vehicle::STATUS_INACTIVE,
                ],
                true
            )
        ) {
            return $this->sendError(
                'This vehicle is not available for Driver assignment.',
                [],
                422
            );
        }

        $driver = Driver::query()
            ->with('user')
            ->find(
                $request->integer(
                    'driver_id'
                )
            );

        if (
            !$driver ||
            $driver->status !==
                Driver::STATUS_ACTIVE ||
            !$driver->user ||
            $driver->user->status !==
                'active' ||
            !(bool) $driver->user->is_active
        ) {
            return $this->sendError(
                'Only an active Driver can be assigned.',
                [],
                422
            );
        }

        $otherVehicle = Vehicle::query()
            ->where(
                'assigned_driver_id',
                $driver->id
            )
            ->where(
                'id',
                '!=',
                $vehicle->id
            )
            ->exists();

        if ($otherVehicle) {
            return $this->sendError(
                'This Driver is already assigned to another vehicle.',
                [],
                422
            );
        }

        if (
            $vehicle->assigned_driver_id &&
            $vehicle->assigned_driver_id !==
                $driver->id
        ) {
            return $this->sendError(
                'This vehicle is already assigned to another Driver.',
                [],
                422
            );
        }

        DB::transaction(function () use (
            $request,
            $vehicle,
            $driver
        ) {
            $vehicle->update([
                'assigned_driver_id' =>
                    $driver->id,

                'status' =>
                    Vehicle::STATUS_ASSIGNED,

                'status_changed_by' =>
                    $request->user()->id,

                'status_changed_at' =>
                    now(),

                'updated_by' =>
                    $request->user()->id,
            ]);
        });

        return $this->sendResponse(
            $this->data(
                $vehicle->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Driver assigned to vehicle successfully.'
        );
    }

    public function unassignDriver(
        Request $request,
        Vehicle $vehicle
    ): JsonResponse {
        if ($request->user()->role !== 'admin') {
            return $this->sendError(
                'You are not allowed to unassign Drivers.',
                [],
                403
            );
        }

        if (
            $vehicle->status ===
            Vehicle::STATUS_IN_TRIP
        ) {
            return $this->sendError(
                'A vehicle currently in a trip cannot be unassigned.',
                [],
                422
            );
        }

        if (!$vehicle->assigned_driver_id) {
            return $this->sendError(
                'This vehicle does not have an assigned Driver.',
                [],
                422
            );
        }

        $vehicle->update([
            'assigned_driver_id' =>
                null,

            'status' =>
                Vehicle::STATUS_AVAILABLE,

            'status_changed_by' =>
                $request->user()->id,

            'status_changed_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $vehicle->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Driver unassigned successfully.'
        );
    }

    public function changeStatus(
        UpdateVehicleStatusRequest $request,
        Vehicle $vehicle
    ): JsonResponse {
        if (
            $vehicle->status ===
            Vehicle::STATUS_IN_TRIP
        ) {
            return $this->sendError(
                'A vehicle currently in a trip cannot have its status changed manually.',
                [],
                422
            );
        }

        if ($vehicle->assigned_driver_id) {
            return $this->sendError(
                'Unassign the Driver before changing the vehicle availability status.',
                [],
                422
            );
        }

        $vehicle->update([
            'status' =>
                $request->string('status')
                    ->toString(),

            'status_changed_by' =>
                $request->user()->id,

            'status_changed_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $vehicle->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Vehicle status updated successfully.'
        );
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
                ],
                true
            );
    }

    private function canAccess(
        User $user,
        Vehicle $vehicle
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

        if ($user->role !== 'driver') {
            return false;
        }

        $driver = Driver::query()
            ->where(
                'user_id',
                $user->id
            )
            ->first();

        return $driver &&
            $vehicle->assigned_driver_id ===
                $driver->id;
    }

    private function relations(): array
    {
        return [
            'assignedDriver.user:id,name,email,phone,role,status,is_active',

            'creator:id,name',

            'updater:id,name',

            'statusChanger:id,name',
        ];
    }

    private function data(
        Vehicle $vehicle
    ): array {
        return [
            'id' =>
                $vehicle->id,

            'vehicle_code' =>
                $vehicle->vehicle_code,

            'registration_number' =>
                $vehicle->registration_number,

            'vehicle_type' =>
                $vehicle->vehicle_type,

            'make' =>
                $vehicle->make,

            'model' =>
                $vehicle->model,

            'manufacture_year' =>
                $vehicle->manufacture_year,

            'capacity_kg' =>
                $vehicle->capacity_kg !== null
                    ? $this->decimal(
                        $vehicle->capacity_kg
                    )
                    : null,

            'assigned_driver_id' =>
                $vehicle->assigned_driver_id,

            'status' =>
                $vehicle->status,

            'notes' =>
                $vehicle->notes,

            'assigned_driver' =>
                $vehicle->assignedDriver,

            'creator' =>
                $vehicle->creator,

            'updater' =>
                $vehicle->updater,

            'status_changer' =>
                $vehicle->statusChanger,

            'status_changed_at' =>
                $vehicle->status_changed_at
                    ?->toISOString(),
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
