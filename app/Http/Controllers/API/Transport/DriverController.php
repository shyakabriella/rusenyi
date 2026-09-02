<?php

namespace App\Http\Controllers\API\Transport;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Transport\StoreDriverRequest;
use App\Http\Requests\API\Transport\UpdateDriverRequest;
use App\Http\Requests\API\Transport\UpdateDriverStatusRequest;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canRead($user)) {
            return $this->sendError(
                'You are not allowed to view drivers.',
                [],
                403
            );
        }

        $query = Driver::query()
            ->with($this->relations());

        if ($user->role === 'driver') {
            $query->where(
                'user_id',
                $user->id
            );
        }

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->search
            );

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where(
                        'driver_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'license_number',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'user',
                        function (Builder $user) use ($search) {
                            $user
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
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
                    fn (Driver $driver) =>
                        $this->data($driver)
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
        ], 'Drivers retrieved successfully.');
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
                'You are not allowed to view driver summary.',
                [],
                403
            );
        }

        return $this->sendResponse([
            'total_drivers' =>
                Driver::count(),

            'active_drivers' =>
                Driver::where(
                    'status',
                    Driver::STATUS_ACTIVE
                )->count(),

            'inactive_drivers' =>
                Driver::where(
                    'status',
                    Driver::STATUS_INACTIVE
                )->count(),

            'suspended_drivers' =>
                Driver::where(
                    'status',
                    Driver::STATUS_SUSPENDED
                )->count(),

            'assigned_drivers' =>
                Driver::whereHas(
                    'assignedVehicle'
                )->count(),

            'unassigned_drivers' =>
                Driver::whereDoesntHave(
                    'assignedVehicle'
                )->count(),
        ], 'Driver summary retrieved successfully.');
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
                'You are not allowed to access driver lookup.',
                [],
                403
            );
        }

        $drivers = Driver::query()
            ->with([
                'user:id,name,email,phone',
                'assignedVehicle:id,vehicle_code,registration_number,assigned_driver_id,status',
            ])
            ->where(
                'status',
                Driver::STATUS_ACTIVE
            )
            ->whereHas(
                'user',
                fn (Builder $query) =>
                    $query
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
            )
            ->orderBy('driver_code')
            ->get();

        return $this->sendResponse([
            'items' =>
                $drivers
                    ->map(
                        fn (Driver $driver) =>
                            $this->data($driver)
                    )
                    ->values(),
        ], 'Driver lookup retrieved successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'driver') {
            return $this->sendError(
                'Only Drivers can access this profile.',
                [],
                403
            );
        }

        $driver = Driver::query()
            ->with($this->relations())
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

        return $this->sendResponse(
            $this->data($driver),
            'Driver profile retrieved successfully.'
        );
    }

    public function store(
        StoreDriverRequest $request
    ): JsonResponse {
        $user = User::find(
            $request->integer('user_id')
        );

        if (!$this->validDriverUser($user)) {
            return $this->sendError(
                'The selected user must be an active Driver user.',
                [],
                422
            );
        }

        $driver = Driver::create([
            'user_id' =>
                $user->id,

            'license_number' =>
                $request->license_number
                    ? trim(
                        $request->license_number
                    )
                    : null,

            'license_category' =>
                $request->license_category
                    ? trim(
                        $request->license_category
                    )
                    : null,

            'license_expiry_date' =>
                $request->license_expiry_date,

            'status' =>
                Driver::STATUS_ACTIVE,

            'notes' =>
                $request->notes,

            'created_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $driver->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Driver created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        Driver $driver
    ): JsonResponse {
        if (
            !$this->canAccess(
                $request->user(),
                $driver
            )
        ) {
            return $this->sendError(
                'You are not allowed to view this Driver.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $driver->load(
                    $this->relations()
                )
            ),
            'Driver retrieved successfully.'
        );
    }

    public function update(
        UpdateDriverRequest $request,
        Driver $driver
    ): JsonResponse {
        $driver->update([
            'license_number' =>
                $request->license_number
                    ? trim(
                        $request->license_number
                    )
                    : null,

            'license_category' =>
                $request->license_category
                    ? trim(
                        $request->license_category
                    )
                    : null,

            'license_expiry_date' =>
                $request->license_expiry_date,

            'notes' =>
                $request->notes,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $driver->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Driver updated successfully.'
        );
    }

    public function changeStatus(
        UpdateDriverStatusRequest $request,
        Driver $driver
    ): JsonResponse {
        $newStatus =
            $request->string('status')
                ->toString();

        if (
            $newStatus !== Driver::STATUS_ACTIVE &&
            $driver->assignedVehicle()->exists()
        ) {
            return $this->sendError(
                'Unassign the Driver vehicle before changing the Driver to an unavailable status.',
                [],
                422
            );
        }

        $driver->update([
            'status' =>
                $newStatus,

            'status_changed_by' =>
                $request->user()->id,

            'status_changed_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $driver->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Driver status updated successfully.'
        );
    }

    private function validDriverUser(
        ?User $user
    ): bool {
        return $user &&
            $user->role === 'driver' &&
            $user->status === 'active' &&
            (bool) $user->is_active;
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
        Driver $driver
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

        return $user->role === 'driver' &&
            $driver->user_id === $user->id;
    }

    private function relations(): array
    {
        return [
            'user:id,name,email,phone,role,status,is_active',

            'assignedVehicle:id,vehicle_code,registration_number,vehicle_type,capacity_kg,assigned_driver_id,status',

            'creator:id,name',

            'updater:id,name',

            'statusChanger:id,name',
        ];
    }

    private function data(
        Driver $driver
    ): array {
        return [
            'id' =>
                $driver->id,

            'driver_code' =>
                $driver->driver_code,

            'user_id' =>
                $driver->user_id,

            'license_number' =>
                $driver->license_number,

            'license_category' =>
                $driver->license_category,

            'license_expiry_date' =>
                $driver->license_expiry_date
                    ?->toDateString(),

            'status' =>
                $driver->status,

            'notes' =>
                $driver->notes,

            'user' =>
                $driver->user,

            'assigned_vehicle' =>
                $driver->assignedVehicle,

            'creator' =>
                $driver->creator,

            'updater' =>
                $driver->updater,

            'status_changer' =>
                $driver->statusChanger,

            'status_changed_at' =>
                $driver->status_changed_at
                    ?->toISOString(),
        ];
    }
}
