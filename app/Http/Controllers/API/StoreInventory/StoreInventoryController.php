<?php

namespace App\Http\Controllers\API\StoreInventory;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\StoreInventory\CancelStoreInventoryRequest;
use App\Http\Requests\API\StoreInventory\StoreStoreInventoryRequest;
use App\Http\Requests\API\StoreInventory\UpdateStoreInventoryRequest;
use App\Models\CoffeeLot;
use App\Models\StoreInventory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreInventoryController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view store inventory.',
                [],
                403
            );
        }

        $query = StoreInventory::query()
            ->with($this->relations());

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->search
            );

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where(
                        'inventory_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'storage_location',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'coffee_type',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'coffeeLot',
                        fn (Builder $lot) =>
                            $lot->where(
                                'lot_code',
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

        if ($request->filled('coffee_type')) {
            $query->where(
                'coffee_type',
                $request->coffee_type
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

        if ($request->filled('storage_location')) {
            $query->where(
                'storage_location',
                $request->storage_location
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
                    fn (StoreInventory $inventory) =>
                        $this->data($inventory)
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
        ], 'Store inventory retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view store inventory summary.',
                [],
                403
            );
        }

        $active = StoreInventory::query()
            ->where(
                'status',
                StoreInventory::STATUS_ACTIVE
            );

        return $this->sendResponse([
            'active_inventory_records' =>
                (clone $active)->count(),

            'total_stock_kg' =>
                $this->decimal(
                    (clone $active)
                        ->sum(
                            'current_quantity_kg'
                        )
                ),

            'total_initial_stock_kg' =>
                $this->decimal(
                    (clone $active)
                        ->sum(
                            'initial_quantity_kg'
                        )
                ),

            'total_bags' =>
                (int) (
                    (clone $active)
                        ->sum('bag_count')
                ),

            'storage_locations' =>
                (clone $active)
                    ->distinct(
                        'storage_location'
                    )
                    ->count(
                        'storage_location'
                    ),

            'cancelled_records' =>
                StoreInventory::where(
                    'status',
                    StoreInventory::STATUS_CANCELLED
                )->count(),
        ], 'Store inventory summary retrieved successfully.');
    }

    public function eligibleLots(
        Request $request
    ): JsonResponse {
        if (
            !in_array(
                $request->user()->role,
                ['admin', 'store'],
                true
            )
        ) {
            return $this->sendError(
                'You are not allowed to view eligible Coffee Lots.',
                [],
                403
            );
        }

        $lots = CoffeeLot::query()
            ->with([
                'season',
            ])
            ->where(
                'status',
                CoffeeLot::STATUS_ACTIVE
            )
            ->where(
                'processing_stage',
                'received'
            )
            ->where(
                'current_weight_kg',
                '>',
                0
            )
            ->whereDoesntHave(
                'storeInventories',
                fn (Builder $query) =>
                    $query->where(
                        'status',
                        StoreInventory::STATUS_ACTIVE
                    )
            )
            ->latest('id')
            ->limit(200)
            ->get();

        return $this->sendResponse([
            'items' =>
                $lots
                    ->map(
                        fn (CoffeeLot $lot) =>
                            $this->lotData($lot)
                    )
                    ->values(),
        ], 'Eligible Coffee Lots retrieved successfully.');
    }

    public function store(
        StoreStoreInventoryRequest $request
    ): JsonResponse {
        $inventory = DB::transaction(
            function () use ($request) {
                $lot = CoffeeLot::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $request->integer(
                            'coffee_lot_id'
                        )
                    );

                if (
                    $lot->status !==
                    CoffeeLot::STATUS_ACTIVE
                ) {
                    throw ValidationException::withMessages([
                        'coffee_lot_id' => [
                            'Only active Coffee Lots can be received into Store.',
                        ],
                    ]);
                }

                if (
                    $lot->processing_stage !==
                    'received'
                ) {
                    throw ValidationException::withMessages([
                        'coffee_lot_id' => [
                            'Only Coffee Lots at the received stage can be moved into Store.',
                        ],
                    ]);
                }

                if (
                    (float) $lot->current_weight_kg <= 0
                ) {
                    throw ValidationException::withMessages([
                        'coffee_lot_id' => [
                            'This Coffee Lot does not have stock available for storage.',
                        ],
                    ]);
                }

                $exists = StoreInventory::query()
                    ->where(
                        'coffee_lot_id',
                        $lot->id
                    )
                    ->where(
                        'status',
                        StoreInventory::STATUS_ACTIVE
                    )
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'coffee_lot_id' => [
                            'This Coffee Lot already has an active Store Inventory record.',
                        ],
                    ]);
                }

                $receivedAt =
                    $request->received_at
                        ? Carbon::parse(
                            $request->received_at
                        )
                        : now();

                if (
                    $lot->lot_date &&
                    $receivedAt->copy()
                        ->startOfDay()
                        ->lt(
                            Carbon::parse(
                                $lot->lot_date
                            )->startOfDay()
                        )
                ) {
                    throw ValidationException::withMessages([
                        'received_at' => [
                            'Store receipt date cannot be before the Coffee Lot date.',
                        ],
                    ]);
                }

                $location = trim(
                    $request->storage_location
                );

                $inventory =
                    StoreInventory::create([
                        'coffee_lot_id' =>
                            $lot->id,

                        'coffee_season_id' =>
                            $lot->coffee_season_id,

                        'source_type' =>
                            $lot->source_type,

                        'source_id' =>
                            $lot->source_id,

                        'coffee_type' =>
                            $lot->coffee_type,

                        'initial_quantity_kg' =>
                            $lot->current_weight_kg,

                        'current_quantity_kg' =>
                            $lot->current_weight_kg,

                        'bag_count' =>
                            $request->has(
                                'bag_count'
                            )
                                ? $request->bag_count
                                : $lot->bag_count,

                        'storage_location' =>
                            $location,

                        'received_at' =>
                            $receivedAt,

                        'status' =>
                            StoreInventory::STATUS_ACTIVE,

                        'notes' =>
                            $request->notes,

                        'received_by' =>
                            $request->user()->id,

                        'created_by' =>
                            $request->user()->id,
                    ]);

                $lot->update([
                    'processing_stage' =>
                        'stored',

                    'storage_location' =>
                        $location,

                    'bag_count' =>
                        $inventory->bag_count,
                ]);

                return $inventory;
            }
        );

        return $this->sendResponse(
            $this->data(
                $inventory
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Coffee Lot received into Store successfully.',
            201
        );
    }

    public function show(
        Request $request,
        StoreInventory $storeInventory
    ): JsonResponse {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view this Store Inventory record.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $storeInventory
                    ->load(
                        $this->relations()
                    )
            ),
            'Store inventory record retrieved successfully.'
        );
    }

    public function update(
        UpdateStoreInventoryRequest $request,
        StoreInventory $storeInventory
    ): JsonResponse {
        if (
            $storeInventory->status !==
            StoreInventory::STATUS_ACTIVE
        ) {
            return $this->sendError(
                'Only active Store Inventory records can be updated.',
                [],
                422
            );
        }

        $lot = $storeInventory->coffeeLot;

        if (
            !$lot ||
            $lot->processing_stage !==
            'stored'
        ) {
            return $this->sendError(
                'This Coffee Lot has already moved beyond the Store receiving stage.',
                [],
                422
            );
        }

        DB::transaction(
            function () use (
                $request,
                $storeInventory,
                $lot
            ) {
                $location =
                    $request->has(
                        'storage_location'
                    )
                        ? trim(
                            $request->storage_location
                        )
                        : $storeInventory
                            ->storage_location;

                $bagCount =
                    $request->has(
                        'bag_count'
                    )
                        ? $request->bag_count
                        : $storeInventory
                            ->bag_count;

                $storeInventory->update([
                    'storage_location' =>
                        $location,

                    'bag_count' =>
                        $bagCount,

                    'notes' =>
                        $request->has(
                            'notes'
                        )
                            ? $request->notes
                            : $storeInventory
                                ->notes,

                    'updated_by' =>
                        $request->user()->id,
                ]);

                $lot->update([
                    'storage_location' =>
                        $location,

                    'bag_count' =>
                        $bagCount,
                ]);
            }
        );

        return $this->sendResponse(
            $this->data(
                $storeInventory
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Store inventory updated successfully.'
        );
    }

    public function cancel(
        CancelStoreInventoryRequest $request,
        StoreInventory $storeInventory
    ): JsonResponse {
        if (
            $storeInventory->status !==
            StoreInventory::STATUS_ACTIVE
        ) {
            return $this->sendError(
                'Only active Store Inventory records can be cancelled.',
                [],
                422
            );
        }

        $lot = $storeInventory->coffeeLot;

        if (!$lot) {
            return $this->sendError(
                'Coffee Lot could not be found.',
                [],
                422
            );
        }

        if (
            $lot->processing_stage !==
            'stored'
        ) {
            return $this->sendError(
                'This inventory receipt cannot be cancelled because the Coffee Lot has already moved to another processing stage.',
                [],
                422
            );
        }

        if (
            abs(
                (float) $storeInventory
                    ->current_quantity_kg -
                (float) $storeInventory
                    ->initial_quantity_kg
            ) > 0.0001
        ) {
            return $this->sendError(
                'Inventory with stock movements cannot be cancelled.',
                [],
                422
            );
        }

        DB::transaction(
            function () use (
                $request,
                $storeInventory,
                $lot
            ) {
                $storeInventory->update([
                    'status' =>
                        StoreInventory::STATUS_CANCELLED,

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

                $lot->update([
                    'processing_stage' =>
                        'received',

                    'storage_location' =>
                        null,
                ]);
            }
        );

        return $this->sendResponse(
            $this->data(
                $storeInventory
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Store inventory receipt cancelled successfully.'
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
                    'store',
                ],
                true
            );
    }

    private function relations(): array
    {
        return [
            'coffeeLot',
            'season',
            'receiver:id,name,email,phone',
            'creator:id,name',
            'updater:id,name',
            'canceller:id,name',
        ];
    }

    private function data(
        StoreInventory $inventory
    ): array {
        return [
            'id' =>
                $inventory->id,

            'inventory_code' =>
                $inventory->inventory_code,

            'coffee_lot_id' =>
                $inventory->coffee_lot_id,

            'coffee_season_id' =>
                $inventory->coffee_season_id,

            'source_type' =>
                $inventory->source_type,

            'source_id' =>
                $inventory->source_id,

            'coffee_type' =>
                $inventory->coffee_type,

            'initial_quantity_kg' =>
                $inventory->initial_quantity_kg,

            'current_quantity_kg' =>
                $inventory->current_quantity_kg,

            'bag_count' =>
                $inventory->bag_count,

            'storage_location' =>
                $inventory->storage_location,

            'received_at' =>
                $inventory->received_at
                    ?->toISOString(),

            'status' =>
                $inventory->status,

            'notes' =>
                $inventory->notes,

            'coffee_lot' =>
                $inventory->coffeeLot,

            'season' =>
                $inventory->season,

            'receiver' =>
                $inventory->receiver,

            'creator' =>
                $inventory->creator,

            'updater' =>
                $inventory->updater,

            'canceller' =>
                $inventory->canceller,

            'cancelled_at' =>
                $inventory->cancelled_at
                    ?->toISOString(),

            'cancellation_reason' =>
                $inventory->cancellation_reason,
        ];
    }

    private function lotData(
        CoffeeLot $lot
    ): array {
        return [
            'id' =>
                $lot->id,

            'lot_code' =>
                $lot->lot_code,

            'coffee_season_id' =>
                $lot->coffee_season_id,

            'source_type' =>
                $lot->source_type,

            'source_id' =>
                $lot->source_id,

            'coffee_type' =>
                $lot->coffee_type,

            'initial_weight_kg' =>
                $lot->initial_weight_kg,

            'current_weight_kg' =>
                $lot->current_weight_kg,

            'bag_count' =>
                $lot->bag_count,

            'processing_stage' =>
                $lot->processing_stage,

            'status' =>
                $lot->status,

            'lot_date' =>
                $lot->lot_date,

            'season' =>
                $lot->season,
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
