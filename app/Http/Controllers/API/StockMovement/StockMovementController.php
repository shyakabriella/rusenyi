<?php

namespace App\Http\Controllers\API\StockMovement;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\StockMovement\ReverseStockMovementRequest;
use App\Http\Requests\API\StockMovement\StoreStockMovementRequest;
use App\Models\StockMovement;
use App\Models\StoreInventory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Stock Movements.',
                [],
                403
            );
        }

        $query = StockMovement::query()
            ->with($this->relations());

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->search
            );

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where(
                        'movement_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'reason',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'inventory',
                        fn (Builder $inventory) =>
                            $inventory
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

        if ($request->filled('movement_type')) {
            $query->where(
                'movement_type',
                $request->movement_type
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        if ($request->filled('store_inventory_id')) {
            $query->where(
                'store_inventory_id',
                $request->integer(
                    'store_inventory_id'
                )
            );
        }

        if ($request->filled('coffee_lot_id')) {
            $query->where(
                'coffee_lot_id',
                $request->integer(
                    'coffee_lot_id'
                )
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

        if ($request->filled('date_from')) {
            $query->whereDate(
                'posted_at',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'posted_at',
                '<=',
                $request->date_to
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
                    fn (StockMovement $movement) =>
                        $this->data($movement)
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
        ], 'Stock Movements retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Stock Movement summary.',
                [],
                403
            );
        }

        $posted = StockMovement::query()
            ->where(
                'status',
                StockMovement::STATUS_POSTED
            );

        return $this->sendResponse([
            'total_movements' =>
                (clone $posted)->count(),

            'stock_in_kg' =>
                $this->decimal(
                    (clone $posted)
                        ->whereIn(
                            'movement_type',
                            StockMovement::IN_TYPES
                        )
                        ->sum('quantity_kg')
                ),

            'stock_out_kg' =>
                $this->decimal(
                    (clone $posted)
                        ->whereIn(
                            'movement_type',
                            StockMovement::OUT_TYPES
                        )
                        ->sum('quantity_kg')
                ),

            'processing_issued_kg' =>
                $this->decimal(
                    (clone $posted)
                        ->where(
                            'movement_type',
                            StockMovement::TYPE_PROCESSING_ISSUE
                        )
                        ->sum('quantity_kg')
                ),

            'transfers' =>
                (clone $posted)
                    ->where(
                        'movement_type',
                        StockMovement::TYPE_TRANSFER
                    )
                    ->count(),

            'reversals' =>
                (clone $posted)
                    ->where(
                        'movement_type',
                        StockMovement::TYPE_REVERSAL
                    )
                    ->count(),

            'current_stock_kg' =>
                $this->decimal(
                    StoreInventory::query()
                        ->where(
                            'status',
                            StoreInventory::STATUS_ACTIVE
                        )
                        ->sum(
                            'current_quantity_kg'
                        )
                ),
        ], 'Stock Movement summary retrieved successfully.');
    }

    public function inventoryLookup(
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
                'You are not allowed to access inventory lookup.',
                [],
                403
            );
        }

        $items = StoreInventory::query()
            ->with([
                'coffeeLot',
                'season',
            ])
            ->where(
                'status',
                StoreInventory::STATUS_ACTIVE
            )
            ->latest('id')
            ->limit(200)
            ->get();

        return $this->sendResponse([
            'items' =>
                $items
                    ->map(
                        fn (StoreInventory $inventory) =>
                            $this->inventoryData(
                                $inventory
                            )
                    )
                    ->values(),
        ], 'Store Inventory lookup retrieved successfully.');
    }

    public function store(
        StoreStockMovementRequest $request
    ): JsonResponse {
        $movement = DB::transaction(
            function () use ($request) {
                $inventory =
                    StoreInventory::query()
                        ->with('coffeeLot')
                        ->lockForUpdate()
                        ->findOrFail(
                            $request->integer(
                                'store_inventory_id'
                            )
                        );

                if (
                    $inventory->status !==
                    StoreInventory::STATUS_ACTIVE
                ) {
                    throw ValidationException::withMessages([
                        'store_inventory_id' => [
                            'Only active Store Inventory can receive Stock Movements.',
                        ],
                    ]);
                }

                $lot = $inventory->coffeeLot;

                if (!$lot) {
                    throw ValidationException::withMessages([
                        'store_inventory_id' => [
                            'Coffee Lot for this inventory could not be found.',
                        ],
                    ]);
                }

                $type =
                    (string) $request->movement_type;

                $before =
                    (float) $inventory
                        ->current_quantity_kg;

                if (
                    $type ===
                    StockMovement::TYPE_TRANSFER
                ) {
                    return $this->postTransfer(
                        $request,
                        $inventory,
                        $lot,
                        $before
                    );
                }

                if (!$request->filled('quantity_kg')) {
                    throw ValidationException::withMessages([
                        'quantity_kg' => [
                            'Quantity is required for this Stock Movement.',
                        ],
                    ]);
                }

                $quantity =
                    round(
                        (float) $request->quantity_kg,
                        2
                    );

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'quantity_kg' => [
                            'Quantity must be greater than zero.',
                        ],
                    ]);
                }

                if (
                    in_array(
                        $type,
                        StockMovement::IN_TYPES,
                        true
                    )
                ) {
                    $after =
                        $before +
                        $quantity;
                } elseif (
                    in_array(
                        $type,
                        StockMovement::OUT_TYPES,
                        true
                    )
                ) {
                    if ($quantity > $before) {
                        throw ValidationException::withMessages([
                            'quantity_kg' => [
                                'Stock Movement quantity exceeds the available inventory quantity.',
                            ],
                        ]);
                    }

                    $after =
                        $before -
                        $quantity;
                } else {
                    throw ValidationException::withMessages([
                        'movement_type' => [
                            'Invalid Stock Movement type.',
                        ],
                    ]);
                }

                $movement =
                    StockMovement::create([
                        'store_inventory_id' =>
                            $inventory->id,

                        'coffee_lot_id' =>
                            $inventory->coffee_lot_id,

                        'coffee_season_id' =>
                            $inventory->coffee_season_id,

                        'movement_type' =>
                            $type,

                        'quantity_kg' =>
                            $quantity,

                        'quantity_before_kg' =>
                            $before,

                        'quantity_after_kg' =>
                            $after,

                        'from_location' =>
                            $inventory->storage_location,

                        'to_location' =>
                            $inventory->storage_location,

                        'reference_type' =>
                            $request->reference_type,

                        'reference_id' =>
                            $request->reference_id,

                        'reason' =>
                            trim(
                                $request->reason
                            ),

                        'notes' =>
                            $request->notes,

                        'status' =>
                            StockMovement::STATUS_POSTED,

                        'posted_by' =>
                            $request->user()->id,

                        'posted_at' =>
                            now(),
                    ]);

                $inventory->update([
                    'current_quantity_kg' =>
                        $after,
                ]);

                $lot->update([
                    'current_weight_kg' =>
                        $after,
                ]);

                return $movement;
            }
        );

        return $this->sendResponse(
            $this->data(
                $movement
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Stock Movement posted successfully.',
            201
        );
    }

    public function show(
        Request $request,
        StockMovement $stockMovement
    ): JsonResponse {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view this Stock Movement.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $stockMovement
                    ->load(
                        $this->relations()
                    )
            ),
            'Stock Movement retrieved successfully.'
        );
    }

    public function reverse(
        ReverseStockMovementRequest $request,
        StockMovement $stockMovement
    ): JsonResponse {
        if (
            $stockMovement->status !==
            StockMovement::STATUS_POSTED
        ) {
            return $this->sendError(
                'Only posted Stock Movements can be reversed.',
                [],
                422
            );
        }

        if (
            $stockMovement->movement_type ===
            StockMovement::TYPE_REVERSAL
        ) {
            return $this->sendError(
                'A reversal movement cannot be reversed.',
                [],
                422
            );
        }

        if (
            $stockMovement->movement_type ===
                StockMovement::TYPE_PROCESSING_ISSUE &&
            $stockMovement->reference_type ===
                'processing_batch'
        ) {
            return $this->sendError(
                'Processing issues must be reversed by cancelling the related Processing Batch.',
                [],
                422
            );
        }

        $reversal = DB::transaction(
            function () use (
                $request,
                $stockMovement
            ) {
                $inventory =
                    StoreInventory::query()
                        ->with('coffeeLot')
                        ->lockForUpdate()
                        ->findOrFail(
                            $stockMovement
                                ->store_inventory_id
                        );

                if (
                    $inventory->status !==
                    StoreInventory::STATUS_ACTIVE
                ) {
                    throw ValidationException::withMessages([
                        'movement' => [
                            'The related Store Inventory is no longer active.',
                        ],
                    ]);
                }

                $latest =
                    StockMovement::query()
                        ->where(
                            'store_inventory_id',
                            $inventory->id
                        )
                        ->where(
                            'status',
                            StockMovement::STATUS_POSTED
                        )
                        ->where(
                            'movement_type',
                            '!=',
                            StockMovement::TYPE_REVERSAL
                        )
                        ->latest('id')
                        ->first();

                if (
                    !$latest ||
                    $latest->id !==
                    $stockMovement->id
                ) {
                    throw ValidationException::withMessages([
                        'movement' => [
                            'Only the latest posted Stock Movement for this inventory can be reversed.',
                        ],
                    ]);
                }

                $lot = $inventory->coffeeLot;

                if (!$lot) {
                    throw ValidationException::withMessages([
                        'movement' => [
                            'Coffee Lot could not be found.',
                        ],
                    ]);
                }

                $before =
                    (float) $inventory
                        ->current_quantity_kg;

                if (
                    $stockMovement->movement_type ===
                    StockMovement::TYPE_TRANSFER
                ) {
                    $reversal =
                        StockMovement::create([
                            'store_inventory_id' =>
                                $inventory->id,

                            'coffee_lot_id' =>
                                $inventory->coffee_lot_id,

                            'coffee_season_id' =>
                                $inventory->coffee_season_id,

                            'movement_type' =>
                                StockMovement::TYPE_REVERSAL,

                            'quantity_kg' =>
                                0,

                            'quantity_before_kg' =>
                                $before,

                            'quantity_after_kg' =>
                                $before,

                            'from_location' =>
                                $stockMovement->to_location,

                            'to_location' =>
                                $stockMovement->from_location,

                            'reverses_stock_movement_id' =>
                                $stockMovement->id,

                            'reason' =>
                                'Reversal of ' .
                                $stockMovement->movement_code,

                            'notes' =>
                                $request->reversal_reason,

                            'status' =>
                                StockMovement::STATUS_POSTED,

                            'posted_by' =>
                                $request->user()->id,

                            'posted_at' =>
                                now(),
                        ]);

                    $inventory->update([
                        'storage_location' =>
                            $stockMovement
                                ->from_location,
                    ]);

                    $lot->update([
                        'storage_location' =>
                            $stockMovement
                                ->from_location,
                    ]);
                } else {
                    $originalBefore =
                        (float) $stockMovement
                            ->quantity_before_kg;

                    $after =
                        $originalBefore;

                    $reversal =
                        StockMovement::create([
                            'store_inventory_id' =>
                                $inventory->id,

                            'coffee_lot_id' =>
                                $inventory->coffee_lot_id,

                            'coffee_season_id' =>
                                $inventory->coffee_season_id,

                            'movement_type' =>
                                StockMovement::TYPE_REVERSAL,

                            'quantity_kg' =>
                                $stockMovement->quantity_kg,

                            'quantity_before_kg' =>
                                $before,

                            'quantity_after_kg' =>
                                $after,

                            'from_location' =>
                                $inventory->storage_location,

                            'to_location' =>
                                $inventory->storage_location,

                            'reverses_stock_movement_id' =>
                                $stockMovement->id,

                            'reason' =>
                                'Reversal of ' .
                                $stockMovement->movement_code,

                            'notes' =>
                                $request->reversal_reason,

                            'status' =>
                                StockMovement::STATUS_POSTED,

                            'posted_by' =>
                                $request->user()->id,

                            'posted_at' =>
                                now(),
                        ]);

                    $inventory->update([
                        'current_quantity_kg' =>
                            $after,
                    ]);

                    $lot->update([
                        'current_weight_kg' =>
                            $after,
                    ]);
                }

                $stockMovement->update([
                    'status' =>
                        StockMovement::STATUS_REVERSED,

                    'reversed_by' =>
                        $request->user()->id,

                    'reversed_at' =>
                        now(),

                    'reversal_reason' =>
                        trim(
                            $request->reversal_reason
                        ),
                ]);

                return $reversal;
            }
        );

        return $this->sendResponse(
            $this->data(
                $reversal
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Stock Movement reversed successfully.'
        );
    }

    private function postTransfer(
        StoreStockMovementRequest $request,
        StoreInventory $inventory,
        $lot,
        float $before
    ): StockMovement {
        if (!$request->filled('to_location')) {
            throw ValidationException::withMessages([
                'to_location' => [
                    'Destination storage location is required for a transfer.',
                ],
            ]);
        }

        $fromLocation =
            trim(
                $inventory->storage_location
            );

        $toLocation =
            trim(
                $request->to_location
            );

        if (
            mb_strtolower($fromLocation) ===
            mb_strtolower($toLocation)
        ) {
            throw ValidationException::withMessages([
                'to_location' => [
                    'Destination location must be different from the current storage location.',
                ],
            ]);
        }

        $movement =
            StockMovement::create([
                'store_inventory_id' =>
                    $inventory->id,

                'coffee_lot_id' =>
                    $inventory->coffee_lot_id,

                'coffee_season_id' =>
                    $inventory->coffee_season_id,

                'movement_type' =>
                    StockMovement::TYPE_TRANSFER,

                'quantity_kg' =>
                    0,

                'quantity_before_kg' =>
                    $before,

                'quantity_after_kg' =>
                    $before,

                'from_location' =>
                    $fromLocation,

                'to_location' =>
                    $toLocation,

                'reference_type' =>
                    $request->reference_type,

                'reference_id' =>
                    $request->reference_id,

                'reason' =>
                    trim(
                        $request->reason
                    ),

                'notes' =>
                    $request->notes,

                'status' =>
                    StockMovement::STATUS_POSTED,

                'posted_by' =>
                    $request->user()->id,

                'posted_at' =>
                    now(),
            ]);

        $inventory->update([
            'storage_location' =>
                $toLocation,
        ]);

        $lot->update([
            'storage_location' =>
                $toLocation,
        ]);

        return $movement;
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
            'inventory',
            'coffeeLot',
            'season',
            'poster:id,name,email,phone',
            'reverser:id,name',
            'reversedMovement',
        ];
    }

    private function data(
        StockMovement $movement
    ): array {
        return [
            'id' =>
                $movement->id,

            'movement_code' =>
                $movement->movement_code,

            'store_inventory_id' =>
                $movement->store_inventory_id,

            'coffee_lot_id' =>
                $movement->coffee_lot_id,

            'coffee_season_id' =>
                $movement->coffee_season_id,

            'movement_type' =>
                $movement->movement_type,

            'quantity_kg' =>
                $movement->quantity_kg,

            'quantity_before_kg' =>
                $movement->quantity_before_kg,

            'quantity_after_kg' =>
                $movement->quantity_after_kg,

            'from_location' =>
                $movement->from_location,

            'to_location' =>
                $movement->to_location,

            'reference_type' =>
                $movement->reference_type,

            'reference_id' =>
                $movement->reference_id,

            'reverses_stock_movement_id' =>
                $movement->reverses_stock_movement_id,

            'reason' =>
                $movement->reason,

            'notes' =>
                $movement->notes,

            'status' =>
                $movement->status,

            'posted_at' =>
                $movement->posted_at
                    ?->toISOString(),

            'reversed_at' =>
                $movement->reversed_at
                    ?->toISOString(),

            'reversal_reason' =>
                $movement->reversal_reason,

            'inventory' =>
                $movement->inventory,

            'coffee_lot' =>
                $movement->coffeeLot,

            'season' =>
                $movement->season,

            'poster' =>
                $movement->poster,

            'reverser' =>
                $movement->reverser,

            'reversed_movement' =>
                $movement->reversedMovement,
        ];
    }

    private function inventoryData(
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

            'coffee_type' =>
                $inventory->coffee_type,

            'current_quantity_kg' =>
                $inventory->current_quantity_kg,

            'bag_count' =>
                $inventory->bag_count,

            'storage_location' =>
                $inventory->storage_location,

            'status' =>
                $inventory->status,

            'coffee_lot' =>
                $inventory->coffeeLot,

            'season' =>
                $inventory->season,
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
