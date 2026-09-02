<?php

namespace App\Http\Controllers\API\Processing;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Processing\CancelProcessingBatchRequest;
use App\Http\Requests\API\Processing\StoreProcessingBatchRequest;
use App\Http\Requests\API\Processing\UpdateProcessingBatchRequest;
use App\Models\CoffeeLot;
use App\Models\ProcessingBatch;
use App\Models\StockMovement;
use App\Models\StoreInventory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessingBatchController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Coffee Processing.',
                [],
                403
            );
        }

        $query = ProcessingBatch::query()
            ->with($this->relations());

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->search
            );

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where(
                        'batch_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'process_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'inventory',
                        fn (Builder $inventory) =>
                            $inventory->where(
                                'inventory_code',
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

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        if ($request->filled('process_name')) {
            $query->where(
                'process_name',
                $request->process_name
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

        if ($request->filled('coffee_lot_id')) {
            $query->where(
                'coffee_lot_id',
                $request->integer(
                    'coffee_lot_id'
                )
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'created_at',
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
                    fn (ProcessingBatch $batch) =>
                        $this->data($batch)
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
        ], 'Processing batches retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Coffee Processing summary.',
                [],
                403
            );
        }

        return $this->sendResponse([
            'total_batches' =>
                ProcessingBatch::count(),

            'draft_batches' =>
                ProcessingBatch::where(
                    'status',
                    ProcessingBatch::STATUS_DRAFT
                )->count(),

            'in_progress_batches' =>
                ProcessingBatch::where(
                    'status',
                    ProcessingBatch::STATUS_IN_PROGRESS
                )->count(),

            'completed_batches' =>
                ProcessingBatch::where(
                    'status',
                    ProcessingBatch::STATUS_COMPLETED
                )->count(),

            'cancelled_batches' =>
                ProcessingBatch::where(
                    'status',
                    ProcessingBatch::STATUS_CANCELLED
                )->count(),

            'issued_to_processing_kg' =>
                $this->decimal(
                    ProcessingBatch::query()
                        ->whereIn(
                            'status',
                            [
                                ProcessingBatch::STATUS_IN_PROGRESS,
                                ProcessingBatch::STATUS_COMPLETED,
                            ]
                        )
                        ->sum(
                            'input_quantity_kg'
                        )
                ),

            'currently_processing_kg' =>
                $this->decimal(
                    ProcessingBatch::query()
                        ->where(
                            'status',
                            ProcessingBatch::STATUS_IN_PROGRESS
                        )
                        ->sum(
                            'input_quantity_kg'
                        )
                ),
        ], 'Coffee Processing summary retrieved successfully.');
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
                'You are not allowed to access processing inventory lookup.',
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
            ->where(
                'current_quantity_kg',
                '>',
                0
            )
            ->whereDoesntHave(
                'processingBatches',
                function (Builder $query) {
                    $query->whereIn(
                        'status',
                        [
                            ProcessingBatch::STATUS_DRAFT,
                            ProcessingBatch::STATUS_IN_PROGRESS,
                        ]
                    );
                }
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
        ], 'Processing inventory lookup retrieved successfully.');
    }

    public function store(
        StoreProcessingBatchRequest $request
    ): JsonResponse {
        $batch = DB::transaction(
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

                $this->validateInventory(
                    $inventory
                );

                $quantity = round(
                    (float) $request
                        ->input_quantity_kg,
                    2
                );

                if (
                    $quantity >
                    (float) $inventory
                        ->current_quantity_kg
                ) {
                    throw ValidationException::withMessages([
                        'input_quantity_kg' => [
                            'Processing quantity exceeds available Store Inventory.',
                        ],
                    ]);
                }

                $activeExists =
                    ProcessingBatch::query()
                        ->where(
                            'store_inventory_id',
                            $inventory->id
                        )
                        ->whereIn(
                            'status',
                            [
                                ProcessingBatch::STATUS_DRAFT,
                                ProcessingBatch::STATUS_IN_PROGRESS,
                            ]
                        )
                        ->exists();

                if ($activeExists) {
                    throw ValidationException::withMessages([
                        'store_inventory_id' => [
                            'This inventory already has an active Processing Batch.',
                        ],
                    ]);
                }

                return ProcessingBatch::create([
                    'store_inventory_id' =>
                        $inventory->id,

                    'coffee_lot_id' =>
                        $inventory->coffee_lot_id,

                    'coffee_season_id' =>
                        $inventory->coffee_season_id,

                    'process_name' =>
                        trim(
                            $request->process_name
                        ),

                    'input_quantity_kg' =>
                        $quantity,

                    'source_storage_location' =>
                        $inventory
                            ->storage_location,

                    'planned_start_at' =>
                        $request
                            ->planned_start_at,

                    'status' =>
                        ProcessingBatch::STATUS_DRAFT,

                    'notes' =>
                        $request->notes,

                    'created_by' =>
                        $request->user()->id,
                ]);
            }
        );

        return $this->sendResponse(
            $this->data(
                $batch
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Processing batch created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        ProcessingBatch $processingBatch
    ): JsonResponse {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view this Processing Batch.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $processingBatch
                    ->load(
                        $this->relations()
                    )
            ),
            'Processing batch retrieved successfully.'
        );
    }

    public function update(
        UpdateProcessingBatchRequest $request,
        ProcessingBatch $processingBatch
    ): JsonResponse {
        if (
            $processingBatch->status !==
            ProcessingBatch::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft Processing Batches can be updated.',
                [],
                422
            );
        }

        $inventory =
            StoreInventory::find(
                $processingBatch
                    ->store_inventory_id
            );

        if (!$inventory) {
            return $this->sendError(
                'Store Inventory could not be found.',
                [],
                422
            );
        }

        if (
            $request->has(
                'input_quantity_kg'
            )
        ) {
            $quantity = round(
                (float) $request
                    ->input_quantity_kg,
                2
            );

            if (
                $quantity >
                (float) $inventory
                    ->current_quantity_kg
            ) {
                return $this->sendError(
                    'Processing quantity exceeds available Store Inventory.',
                    [],
                    422
                );
            }
        }

        $processingBatch->update([
            'process_name' =>
                $request->has(
                    'process_name'
                )
                    ? trim(
                        $request->process_name
                    )
                    : $processingBatch
                        ->process_name,

            'input_quantity_kg' =>
                $request->has(
                    'input_quantity_kg'
                )
                    ? round(
                        (float) $request
                            ->input_quantity_kg,
                        2
                    )
                    : $processingBatch
                        ->input_quantity_kg,

            'planned_start_at' =>
                $request->has(
                    'planned_start_at'
                )
                    ? $request
                        ->planned_start_at
                    : $processingBatch
                        ->planned_start_at,

            'notes' =>
                $request->has('notes')
                    ? $request->notes
                    : $processingBatch->notes,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $processingBatch
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Processing batch updated successfully.'
        );
    }

    public function start(
        Request $request,
        ProcessingBatch $processingBatch
    ): JsonResponse {
        if (
            !in_array(
                $request->user()->role,
                ['admin', 'store'],
                true
            )
        ) {
            return $this->sendError(
                'You are not allowed to start Coffee Processing.',
                [],
                403
            );
        }

        $batch = DB::transaction(
            function () use (
                $request,
                $processingBatch
            ) {
                $batch =
                    ProcessingBatch::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $processingBatch->id
                        );

                if (
                    $batch->status !==
                    ProcessingBatch::STATUS_DRAFT
                ) {
                    throw ValidationException::withMessages([
                        'processing_batch' => [
                            'Only draft Processing Batches can be started.',
                        ],
                    ]);
                }

                $inventory =
                    StoreInventory::query()
                        ->with('coffeeLot')
                        ->lockForUpdate()
                        ->findOrFail(
                            $batch
                                ->store_inventory_id
                        );

                $this->validateInventory(
                    $inventory
                );

                $otherRunning =
                    ProcessingBatch::query()
                        ->where(
                            'store_inventory_id',
                            $inventory->id
                        )
                        ->where(
                            'status',
                            ProcessingBatch::STATUS_IN_PROGRESS
                        )
                        ->where(
                            'id',
                            '!=',
                            $batch->id
                        )
                        ->exists();

                if ($otherRunning) {
                    throw ValidationException::withMessages([
                        'processing_batch' => [
                            'Another Processing Batch is already running for this inventory.',
                        ],
                    ]);
                }

                $quantity =
                    (float) $batch
                        ->input_quantity_kg;

                $before =
                    (float) $inventory
                        ->current_quantity_kg;

                if ($quantity > $before) {
                    throw ValidationException::withMessages([
                        'input_quantity_kg' => [
                            'Available Store Inventory is no longer enough for this Processing Batch.',
                        ],
                    ]);
                }

                $after = round(
                    $before - $quantity,
                    2
                );

                $movement =
                    StockMovement::create([
                        'store_inventory_id' =>
                            $inventory->id,

                        'coffee_lot_id' =>
                            $inventory
                                ->coffee_lot_id,

                        'coffee_season_id' =>
                            $inventory
                                ->coffee_season_id,

                        'movement_type' =>
                            StockMovement::TYPE_PROCESSING_ISSUE,

                        'quantity_kg' =>
                            $quantity,

                        'quantity_before_kg' =>
                            $before,

                        'quantity_after_kg' =>
                            $after,

                        'from_location' =>
                            $inventory
                                ->storage_location,

                        'to_location' =>
                            null,

                        'reference_type' =>
                            'processing_batch',

                        'reference_id' =>
                            $batch->id,

                        'reason' =>
                            'Issued to processing batch ' .
                            $batch->batch_code,

                        'notes' =>
                            $batch->notes,

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

                $inventory
                    ->coffeeLot
                    ->update([
                        'current_weight_kg' =>
                            $after,

                        'processing_stage' =>
                            'processing',
                    ]);

                $batch->update([
                    'status' =>
                        ProcessingBatch::STATUS_IN_PROGRESS,

                    'processing_issue_movement_id' =>
                        $movement->id,

                    'started_by' =>
                        $request->user()->id,

                    'started_at' =>
                        now(),

                    'updated_by' =>
                        $request->user()->id,
                ]);

                return $batch;
            }
        );

        return $this->sendResponse(
            $this->data(
                $batch
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Coffee Processing started successfully.'
        );
    }

    public function complete(
        Request $request,
        ProcessingBatch $processingBatch
    ): JsonResponse {
        if (
            !in_array(
                $request->user()->role,
                ['admin', 'store'],
                true
            )
        ) {
            return $this->sendError(
                'You are not allowed to complete Coffee Processing.',
                [],
                403
            );
        }

        if (
            $processingBatch->status !==
            ProcessingBatch::STATUS_IN_PROGRESS
        ) {
            return $this->sendError(
                'Only Processing Batches that are in progress can be completed.',
                [],
                422
            );
        }

        if (
            !$processingBatch
                ->processing_issue_movement_id
        ) {
            return $this->sendError(
                'Processing stock issue could not be found.',
                [],
                422
            );
        }

        $processingBatch->update([
            'status' =>
                ProcessingBatch::STATUS_COMPLETED,

            'completed_by' =>
                $request->user()->id,

            'completed_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $processingBatch
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Coffee Processing completed successfully.'
        );
    }

    public function cancel(
        CancelProcessingBatchRequest $request,
        ProcessingBatch $processingBatch
    ): JsonResponse {
        if (
            $processingBatch->status ===
            ProcessingBatch::STATUS_COMPLETED
        ) {
            return $this->sendError(
                'Completed Processing Batches cannot be cancelled.',
                [],
                422
            );
        }

        if (
            $processingBatch->status ===
            ProcessingBatch::STATUS_CANCELLED
        ) {
            return $this->sendError(
                'This Processing Batch is already cancelled.',
                [],
                422
            );
        }

        DB::transaction(
            function () use (
                $request,
                $processingBatch
            ) {
                $batch =
                    ProcessingBatch::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $processingBatch->id
                        );

                if (
                    $batch->status ===
                    ProcessingBatch::STATUS_IN_PROGRESS
                ) {
                    $this->reverseProcessingIssue(
                        $request,
                        $batch
                    );
                }

                $batch->update([
                    'status' =>
                        ProcessingBatch::STATUS_CANCELLED,

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
            }
        );

        return $this->sendResponse(
            $this->data(
                $processingBatch
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Processing batch cancelled successfully.'
        );
    }

    private function reverseProcessingIssue(
        Request $request,
        ProcessingBatch $batch
    ): void {
        $movement =
            StockMovement::query()
                ->lockForUpdate()
                ->findOrFail(
                    $batch
                        ->processing_issue_movement_id
                );

        if (
            $movement->status !==
            StockMovement::STATUS_POSTED
        ) {
            throw ValidationException::withMessages([
                'processing_batch' => [
                    'Processing stock issue has already been reversed.',
                ],
            ]);
        }

        $inventory =
            StoreInventory::query()
                ->with('coffeeLot')
                ->lockForUpdate()
                ->findOrFail(
                    $batch->store_inventory_id
                );

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
            $movement->id
        ) {
            throw ValidationException::withMessages([
                'processing_batch' => [
                    'This Processing Batch cannot be cancelled because later Stock Movements already exist.',
                ],
            ]);
        }

        $before =
            (float) $inventory
                ->current_quantity_kg;

        $after =
            (float) $movement
                ->quantity_before_kg;

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
                $movement->quantity_kg,

            'quantity_before_kg' =>
                $before,

            'quantity_after_kg' =>
                $after,

            'from_location' =>
                $inventory
                    ->storage_location,

            'to_location' =>
                $inventory
                    ->storage_location,

            'reverses_stock_movement_id' =>
                $movement->id,

            'reason' =>
                'Cancellation of processing batch ' .
                $batch->batch_code,

            'notes' =>
                $request
                    ->cancellation_reason,

            'status' =>
                StockMovement::STATUS_POSTED,

            'posted_by' =>
                $request->user()->id,

            'posted_at' =>
                now(),
        ]);

        $movement->update([
            'status' =>
                StockMovement::STATUS_REVERSED,

            'reversed_by' =>
                $request->user()->id,

            'reversed_at' =>
                now(),

            'reversal_reason' =>
                trim(
                    $request
                        ->cancellation_reason
                ),
        ]);

        $inventory->update([
            'current_quantity_kg' =>
                $after,
        ]);

        if ($inventory->coffeeLot) {
            $inventory->coffeeLot->update([
                'current_weight_kg' =>
                    $after,

                'processing_stage' =>
                    'stored',
            ]);
        }
    }

    private function validateInventory(
        StoreInventory $inventory
    ): void {
        if (
            $inventory->status !==
            StoreInventory::STATUS_ACTIVE
        ) {
            throw ValidationException::withMessages([
                'store_inventory_id' => [
                    'Only active Store Inventory can be processed.',
                ],
            ]);
        }

        if (
            (float) $inventory
                ->current_quantity_kg <= 0
        ) {
            throw ValidationException::withMessages([
                'store_inventory_id' => [
                    'This Store Inventory has no coffee available for processing.',
                ],
            ]);
        }

        if (!$inventory->coffeeLot) {
            throw ValidationException::withMessages([
                'store_inventory_id' => [
                    'Coffee Lot could not be found.',
                ],
            ]);
        }

        if (
            $inventory
                ->coffeeLot
                ->status !==
            CoffeeLot::STATUS_ACTIVE
        ) {
            throw ValidationException::withMessages([
                'store_inventory_id' => [
                    'Only an active Coffee Lot can be processed.',
                ],
            ]);
        }
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
            'processingIssueMovement',
            'creator:id,name',
            'updater:id,name',
            'starter:id,name',
            'completer:id,name',
            'canceller:id,name',
        ];
    }

    private function data(
        ProcessingBatch $batch
    ): array {
        return [
            'id' =>
                $batch->id,

            'batch_code' =>
                $batch->batch_code,

            'store_inventory_id' =>
                $batch->store_inventory_id,

            'coffee_lot_id' =>
                $batch->coffee_lot_id,

            'coffee_season_id' =>
                $batch->coffee_season_id,

            'process_name' =>
                $batch->process_name,

            'input_quantity_kg' =>
                $batch->input_quantity_kg,

            'source_storage_location' =>
                $batch
                    ->source_storage_location,

            'planned_start_at' =>
                $batch
                    ->planned_start_at
                    ?->toISOString(),

            'status' =>
                $batch->status,

            'processing_issue_movement_id' =>
                $batch
                    ->processing_issue_movement_id,

            'notes' =>
                $batch->notes,

            'started_at' =>
                $batch->started_at
                    ?->toISOString(),

            'completed_at' =>
                $batch->completed_at
                    ?->toISOString(),

            'cancelled_at' =>
                $batch->cancelled_at
                    ?->toISOString(),

            'cancellation_reason' =>
                $batch
                    ->cancellation_reason,

            'inventory' =>
                $batch->inventory,

            'coffee_lot' =>
                $batch->coffeeLot,

            'season' =>
                $batch->season,

            'processing_issue_movement' =>
                $batch
                    ->processingIssueMovement,

            'creator' =>
                $batch->creator,

            'updater' =>
                $batch->updater,

            'starter' =>
                $batch->starter,

            'completer' =>
                $batch->completer,

            'canceller' =>
                $batch->canceller,
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
                $inventory
                    ->current_quantity_kg,

            'bag_count' =>
                $inventory->bag_count,

            'storage_location' =>
                $inventory
                    ->storage_location,

            'status' =>
                $inventory->status,

            'coffee_lot' =>
                $inventory->coffeeLot,

            'season' =>
                $inventory->season,
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
