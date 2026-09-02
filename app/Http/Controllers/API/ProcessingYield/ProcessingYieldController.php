<?php

namespace App\Http\Controllers\API\ProcessingYield;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\ProcessingYield\CancelProcessingYieldRequest;
use App\Http\Requests\API\ProcessingYield\StoreProcessingYieldRequest;
use App\Http\Requests\API\ProcessingYield\UpdateProcessingYieldRequest;
use App\Models\CoffeeLot;
use App\Models\ProcessingBatch;
use App\Models\ProcessingYield;
use App\Models\StoreInventory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessingYieldController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Processing Yield records.',
                [],
                403
            );
        }

        $query = ProcessingYield::query()
            ->with($this->relations());

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->search
            );

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where(
                        'yield_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'output_coffee_type',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'processingBatch',
                        fn (Builder $batch) =>
                            $batch->where(
                                'batch_code',
                                'like',
                                "%{$search}%"
                            )
                    )
                    ->orWhereHas(
                        'sourceCoffeeLot',
                        fn (Builder $lot) =>
                            $lot->where(
                                'lot_code',
                                'like',
                                "%{$search}%"
                            )
                    )
                    ->orWhereHas(
                        'outputCoffeeLot',
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

        if ($request->filled('output_coffee_type')) {
            $query->where(
                'output_coffee_type',
                $request->output_coffee_type
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

        if ($request->filled('processing_batch_id')) {
            $query->where(
                'processing_batch_id',
                $request->integer(
                    'processing_batch_id'
                )
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'yield_date',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'yield_date',
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
                    fn (ProcessingYield $record) =>
                        $this->data($record)
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
        ], 'Processing Yield records retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Processing Yield summary.',
                [],
                403
            );
        }

        $confirmed = ProcessingYield::query()
            ->where(
                'status',
                ProcessingYield::STATUS_CONFIRMED
            );

        return $this->sendResponse([
            'total_records' =>
                ProcessingYield::count(),

            'draft_records' =>
                ProcessingYield::where(
                    'status',
                    ProcessingYield::STATUS_DRAFT
                )->count(),

            'confirmed_records' =>
                (clone $confirmed)->count(),

            'cancelled_records' =>
                ProcessingYield::where(
                    'status',
                    ProcessingYield::STATUS_CANCELLED
                )->count(),

            'total_input_kg' =>
                $this->decimal(
                    (clone $confirmed)
                        ->sum('input_quantity_kg')
                ),

            'total_output_kg' =>
                $this->decimal(
                    (clone $confirmed)
                        ->sum('output_quantity_kg')
                ),

            'total_loss_kg' =>
                $this->decimal(
                    (clone $confirmed)
                        ->sum('loss_quantity_kg')
                ),

            'average_yield_percentage' =>
                $this->decimal(
                    (clone $confirmed)
                        ->avg('yield_percentage')
                ),

            'average_loss_percentage' =>
                $this->decimal(
                    (clone $confirmed)
                        ->avg('loss_percentage')
                ),
        ], 'Processing Yield summary retrieved successfully.');
    }

    public function eligibleBatches(
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
                'You are not allowed to view eligible Processing Batches.',
                [],
                403
            );
        }

        $items = ProcessingBatch::query()
            ->with([
                'inventory',
                'coffeeLot',
                'season',
            ])
            ->where(
                'status',
                ProcessingBatch::STATUS_COMPLETED
            )
            ->whereDoesntHave(
                'yieldRecords',
                fn (Builder $query) =>
                    $query->where(
                        'status',
                        '!=',
                        ProcessingYield::STATUS_CANCELLED
                    )
            )
            ->latest('id')
            ->limit(200)
            ->get();

        return $this->sendResponse([
            'items' =>
                $items
                    ->map(
                        fn (ProcessingBatch $batch) =>
                            $this->batchData($batch)
                    )
                    ->values(),
        ], 'Eligible Processing Batches retrieved successfully.');
    }

    public function store(
        StoreProcessingYieldRequest $request
    ): JsonResponse {
        $record = DB::transaction(
            function () use ($request) {
                $batch = ProcessingBatch::query()
                    ->with([
                        'inventory',
                        'coffeeLot',
                    ])
                    ->lockForUpdate()
                    ->findOrFail(
                        $request->integer(
                            'processing_batch_id'
                        )
                    );

                if (
                    $batch->status !==
                    ProcessingBatch::STATUS_COMPLETED
                ) {
                    throw ValidationException::withMessages([
                        'processing_batch_id' => [
                            'Only completed Processing Batches can have Yield and Loss recorded.',
                        ],
                    ]);
                }

                $existing =
                    ProcessingYield::query()
                        ->where(
                            'processing_batch_id',
                            $batch->id
                        )
                        ->where(
                            'status',
                            '!=',
                            ProcessingYield::STATUS_CANCELLED
                        )
                        ->exists();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'processing_batch_id' => [
                            'This Processing Batch already has an active Yield record.',
                        ],
                    ]);
                }

                $metrics = $this->calculate(
                    (float) $batch->input_quantity_kg,
                    (float) $request->output_quantity_kg
                );

                $yieldDate = $request->yield_date
                    ? Carbon::parse(
                        $request->yield_date
                    )
                    : (
                        $batch->completed_at
                            ? $batch->completed_at->copy()
                            : now()
                    );

                $this->validateYieldDate(
                    $batch,
                    $yieldDate
                );

                return ProcessingYield::create([
                    'processing_batch_id' =>
                        $batch->id,

                    'store_inventory_id' =>
                        $batch->store_inventory_id,

                    'source_coffee_lot_id' =>
                        $batch->coffee_lot_id,

                    'coffee_season_id' =>
                        $batch->coffee_season_id,

                    'input_quantity_kg' =>
                        $batch->input_quantity_kg,

                    'output_quantity_kg' =>
                        $metrics['output'],

                    'loss_quantity_kg' =>
                        $metrics['loss'],

                    'yield_percentage' =>
                        $metrics['yield_percentage'],

                    'loss_percentage' =>
                        $metrics['loss_percentage'],

                    'output_coffee_type' =>
                        trim(
                            $request->output_coffee_type
                        ),

                    'output_processing_stage' =>
                        'received',

                    'output_bag_count' =>
                        $request->output_bag_count,

                    'yield_date' =>
                        $yieldDate->toDateString(),

                    'status' =>
                        ProcessingYield::STATUS_DRAFT,

                    'notes' =>
                        $request->notes,

                    'created_by' =>
                        $request->user()->id,
                ]);
            }
        );

        return $this->sendResponse(
            $this->data(
                $record
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Processing Yield record created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        ProcessingYield $processingYield
    ): JsonResponse {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view this Processing Yield record.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $processingYield
                    ->load(
                        $this->relations()
                    )
            ),
            'Processing Yield record retrieved successfully.'
        );
    }

    public function update(
        UpdateProcessingYieldRequest $request,
        ProcessingYield $processingYield
    ): JsonResponse {
        if (
            $processingYield->status !==
            ProcessingYield::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft Processing Yield records can be updated.',
                [],
                422
            );
        }

        $output = $request->has(
            'output_quantity_kg'
        )
            ? (float) $request->output_quantity_kg
            : (float) $processingYield
                ->output_quantity_kg;

        try {
            $metrics = $this->calculate(
                (float) $processingYield
                    ->input_quantity_kg,
                $output
            );
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $yieldDate = $request->has(
            'yield_date'
        ) && $request->yield_date
            ? Carbon::parse(
                $request->yield_date
            )
            : $processingYield
                ->yield_date
                ->copy();

        $this->validateYieldDate(
            $processingYield->processingBatch,
            $yieldDate
        );

        $processingYield->update([
            'output_quantity_kg' =>
                $metrics['output'],

            'loss_quantity_kg' =>
                $metrics['loss'],

            'yield_percentage' =>
                $metrics['yield_percentage'],

            'loss_percentage' =>
                $metrics['loss_percentage'],

            'output_coffee_type' =>
                $request->has(
                    'output_coffee_type'
                )
                    ? trim(
                        $request->output_coffee_type
                    )
                    : $processingYield
                        ->output_coffee_type,

            'output_bag_count' =>
                $request->has(
                    'output_bag_count'
                )
                    ? $request->output_bag_count
                    : $processingYield
                        ->output_bag_count,

            'yield_date' =>
                $yieldDate->toDateString(),

            'notes' =>
                $request->has('notes')
                    ? $request->notes
                    : $processingYield->notes,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $processingYield
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Processing Yield record updated successfully.'
        );
    }

    public function confirm(
        Request $request,
        ProcessingYield $processingYield
    ): JsonResponse {
        if (
            !in_array(
                $request->user()->role,
                ['admin', 'store'],
                true
            )
        ) {
            return $this->sendError(
                'You are not allowed to confirm Processing Yield.',
                [],
                403
            );
        }

        $record = DB::transaction(
            function () use (
                $request,
                $processingYield
            ) {
                $record =
                    ProcessingYield::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $processingYield->id
                        );

                if (
                    $record->status !==
                    ProcessingYield::STATUS_DRAFT
                ) {
                    throw ValidationException::withMessages([
                        'processing_yield' => [
                            'Only draft Processing Yield records can be confirmed.',
                        ],
                    ]);
                }

                $batch =
                    ProcessingBatch::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $record->processing_batch_id
                        );

                if (
                    $batch->status !==
                    ProcessingBatch::STATUS_COMPLETED
                ) {
                    throw ValidationException::withMessages([
                        'processing_batch' => [
                            'The related Processing Batch must be completed.',
                        ],
                    ]);
                }

                if ($record->output_coffee_lot_id) {
                    throw ValidationException::withMessages([
                        'processing_yield' => [
                            'An output Coffee Lot already exists for this Yield record.',
                        ],
                    ]);
                }

                $inventory =
                    StoreInventory::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $record->store_inventory_id
                        );

                $sourceLot =
                    CoffeeLot::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $record->source_coffee_lot_id
                        );

                $outputLot = CoffeeLot::create([
                    'coffee_season_id' =>
                        $record->coffee_season_id,

                    'source_type' =>
                        'processing_batch',

                    'source_id' =>
                        $batch->id,

                    'coffee_type' =>
                        $record->output_coffee_type,

                    'initial_weight_kg' =>
                        $record->output_quantity_kg,

                    'current_weight_kg' =>
                        $record->output_quantity_kg,

                    'bag_count' =>
                        $record->output_bag_count,

                    'processing_stage' =>
                        'received',

                    'status' =>
                        CoffeeLot::STATUS_ACTIVE,

                    'storage_location' =>
                        null,

                    'lot_date' =>
                        $record->yield_date,

                    'notes' =>
                        'Processed output from ' .
                        $batch->batch_code .
                        ($record->notes
                            ? '. ' . $record->notes
                            : ''),

                    'created_by' =>
                        $request->user()->id,

                    'updated_by' =>
                        $request->user()->id,
                ]);

                $record->update([
                    'output_coffee_lot_id' =>
                        $outputLot->id,

                    'status' =>
                        ProcessingYield::STATUS_CONFIRMED,

                    'confirmed_by' =>
                        $request->user()->id,

                    'confirmed_at' =>
                        now(),

                    'updated_by' =>
                        $request->user()->id,
                ]);

                if (
                    (float) $inventory
                        ->current_quantity_kg > 0
                ) {
                    $sourceLot->update([
                        'processing_stage' =>
                            'stored',
                    ]);
                } else {
                    $sourceLot->update([
                        'processing_stage' =>
                            'processed',
                    ]);
                }

                return $record;
            }
        );

        return $this->sendResponse(
            $this->data(
                $record
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Processing Yield confirmed and output Coffee Lot created successfully.'
        );
    }

    public function cancel(
        CancelProcessingYieldRequest $request,
        ProcessingYield $processingYield
    ): JsonResponse {
        if (
            $processingYield->status !==
            ProcessingYield::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft Processing Yield records can be cancelled.',
                [],
                422
            );
        }

        $processingYield->update([
            'status' =>
                ProcessingYield::STATUS_CANCELLED,

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
                $processingYield
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Processing Yield record cancelled successfully.'
        );
    }

    private function calculate(
        float $input,
        float $output
    ): array {
        if ($input <= 0) {
            throw ValidationException::withMessages([
                'processing_batch_id' => [
                    'Processing input quantity must be greater than zero.',
                ],
            ]);
        }

        if ($output <= 0) {
            throw ValidationException::withMessages([
                'output_quantity_kg' => [
                    'Output quantity must be greater than zero.',
                ],
            ]);
        }

        if ($output > $input) {
            throw ValidationException::withMessages([
                'output_quantity_kg' => [
                    'Output quantity cannot exceed processing input quantity.',
                ],
            ]);
        }

        $output = round(
            $output,
            2
        );

        $loss = round(
            $input - $output,
            2
        );

        $yieldPercentage = round(
            ($output / $input) * 100,
            4
        );

        $lossPercentage = round(
            ($loss / $input) * 100,
            4
        );

        return [
            'output' => $output,
            'loss' => $loss,

            'yield_percentage' =>
                $yieldPercentage,

            'loss_percentage' =>
                $lossPercentage,
        ];
    }

    private function validateYieldDate(
        ProcessingBatch $batch,
        Carbon $yieldDate
    ): void {
        if (
            $batch->completed_at &&
            $yieldDate->copy()
                ->endOfDay()
                ->lt(
                    $batch->completed_at
                )
        ) {
            throw ValidationException::withMessages([
                'yield_date' => [
                    'Yield date cannot be before the Processing Batch completion date.',
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
            'processingBatch',
            'inventory',
            'sourceCoffeeLot',
            'season',
            'outputCoffeeLot',
            'creator:id,name',
            'updater:id,name',
            'confirmer:id,name',
            'canceller:id,name',
        ];
    }

    private function data(
        ProcessingYield $record
    ): array {
        return [
            'id' =>
                $record->id,

            'yield_code' =>
                $record->yield_code,

            'processing_batch_id' =>
                $record->processing_batch_id,

            'store_inventory_id' =>
                $record->store_inventory_id,

            'source_coffee_lot_id' =>
                $record->source_coffee_lot_id,

            'coffee_season_id' =>
                $record->coffee_season_id,

            'input_quantity_kg' =>
                $record->input_quantity_kg,

            'output_quantity_kg' =>
                $record->output_quantity_kg,

            'loss_quantity_kg' =>
                $record->loss_quantity_kg,

            'yield_percentage' =>
                $record->yield_percentage,

            'loss_percentage' =>
                $record->loss_percentage,

            'output_coffee_type' =>
                $record->output_coffee_type,

            'output_processing_stage' =>
                $record->output_processing_stage,

            'output_bag_count' =>
                $record->output_bag_count,

            'yield_date' =>
                $record->yield_date
                    ?->format('Y-m-d'),

            'output_coffee_lot_id' =>
                $record->output_coffee_lot_id,

            'status' =>
                $record->status,

            'notes' =>
                $record->notes,

            'confirmed_at' =>
                $record->confirmed_at
                    ?->toISOString(),

            'cancelled_at' =>
                $record->cancelled_at
                    ?->toISOString(),

            'cancellation_reason' =>
                $record->cancellation_reason,

            'processing_batch' =>
                $record->processingBatch,

            'inventory' =>
                $record->inventory,

            'source_coffee_lot' =>
                $record->sourceCoffeeLot,

            'season' =>
                $record->season,

            'output_coffee_lot' =>
                $record->outputCoffeeLot,

            'creator' =>
                $record->creator,

            'updater' =>
                $record->updater,

            'confirmer' =>
                $record->confirmer,

            'canceller' =>
                $record->canceller,
        ];
    }

    private function batchData(
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
                $batch->source_storage_location,

            'status' =>
                $batch->status,

            'completed_at' =>
                $batch->completed_at
                    ?->toISOString(),

            'inventory' =>
                $batch->inventory,

            'coffee_lot' =>
                $batch->coffeeLot,

            'season' =>
                $batch->season,
        ];
    }

    private function decimal(
        $value
    ): string {
        return number_format(
            (float) ($value ?? 0),
            2,
            '.',
            ''
        );
    }
}
