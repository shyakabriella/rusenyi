<?php

namespace App\Http\Controllers\API\CoffeeLot;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\CoffeeLot\StoreCoffeeLotRequest;
use App\Http\Requests\API\CoffeeLot\UpdateCoffeeLotRequest;
use App\Models\CoffeeLot;
use App\Models\CoffeePurchase;
use App\Models\DirectFarmerDelivery;
use App\Models\FactoryReception;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoffeeLotController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view coffee lots.',
                [],
                403
            );
        }

        $query = CoffeeLot::query()
            ->with($this->relations());

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->search
            );

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where(
                        'lot_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'storage_location',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        foreach ([
            'status',
            'source_type',
            'coffee_type',
            'processing_stage',
            'coffee_season_id',
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
            'items' => collect($items->items())
                ->map(
                    fn (CoffeeLot $lot) =>
                        $this->data($lot)
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
        ], 'Coffee lots retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view coffee lot summary.',
                [],
                403
            );
        }

        $query = CoffeeLot::query();

        if ($request->filled('coffee_season_id')) {
            $query->where(
                'coffee_season_id',
                $request->integer(
                    'coffee_season_id'
                )
            );
        }

        return $this->sendResponse([
            'total_lots' =>
                (clone $query)->count(),

            'active_lots' =>
                (clone $query)
                    ->where(
                        'status',
                        CoffeeLot::STATUS_ACTIVE
                    )
                    ->count(),

            'closed_lots' =>
                (clone $query)
                    ->where(
                        'status',
                        CoffeeLot::STATUS_CLOSED
                    )
                    ->count(),

            'initial_weight_kg' =>
                $this->decimal(
                    (clone $query)
                        ->sum(
                            'initial_weight_kg'
                        )
                ),

            'current_weight_kg' =>
                $this->decimal(
                    (clone $query)
                        ->where(
                            'status',
                            CoffeeLot::STATUS_ACTIVE
                        )
                        ->sum(
                            'current_weight_kg'
                        )
                ),
        ], 'Coffee lot summary retrieved successfully.');
    }

    public function eligibleSources(
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
                'You are not allowed to create coffee lots.',
                [],
                403
            );
        }

        $receptions =
            FactoryReception::query()
                ->with([
                    'agentCollection:id,collection_code',
                    'agent.user:id,name',
                ])
                ->where(
                    'status',
                    FactoryReception::STATUS_CONFIRMED
                )
                ->whereNotExists(
                    function ($query) {
                        $query
                            ->select(DB::raw(1))
                            ->from('coffee_lots')
                            ->whereColumn(
                                'coffee_lots.source_id',
                                'factory_receptions.id'
                            )
                            ->where(
                                'coffee_lots.source_type',
                                CoffeeLot::SOURCE_FACTORY_RECEPTION
                            );
                    }
                )
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn ($reception) => [
                    'source_type' =>
                        CoffeeLot::SOURCE_FACTORY_RECEPTION,

                    'source_id' =>
                        $reception->id,

                    'reference' =>
                        $reception->reception_code,

                    'weight_kg' =>
                        $this->decimal(
                            $reception->factory_weight_kg
                        ),

                    'date' =>
                        $reception->received_at
                            ?->toDateString(),

                    'label' =>
                        $reception->agent?->user?->name
                        ?? 'Agent Reception',
                ]);

        $deliveries =
            DirectFarmerDelivery::query()
                ->with('farmer')
                ->where('status', 'confirmed')
                ->where('payment_status', 'paid')
                ->whereNotExists(
                    function ($query) {
                        $query
                            ->select(DB::raw(1))
                            ->from('coffee_lots')
                            ->whereColumn(
                                'coffee_lots.source_id',
                                'direct_farmer_deliveries.id'
                            )
                            ->where(
                                'coffee_lots.source_type',
                                CoffeeLot::SOURCE_DIRECT_FARMER_DELIVERY
                            );
                    }
                )
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn ($delivery) => [
                    'source_type' =>
                        CoffeeLot::SOURCE_DIRECT_FARMER_DELIVERY,

                    'source_id' =>
                        $delivery->id,

                    'reference' =>
                        $delivery->delivery_code,

                    'weight_kg' =>
                        $this->decimal(
                            $delivery->quantity_kg
                        ),

                    'date' =>
                        $delivery->delivery_date,

                    'label' =>
                        $delivery->farmer?->full_name
                        ?? 'Direct Farmer',
                ]);

        return $this->sendResponse([
            'items' => $receptions
                ->concat($deliveries)
                ->values(),
        ], 'Coffee lot sources retrieved successfully.');
    }

    public function store(
        StoreCoffeeLotRequest $request
    ): JsonResponse {
        if (
            CoffeeLot::query()
                ->where(
                    'source_type',
                    $request->source_type
                )
                ->where(
                    'source_id',
                    $request->integer('source_id')
                )
                ->exists()
        ) {
            return $this->sendError(
                'This source already has a coffee lot.',
                [],
                422
            );
        }

        $source = $this->resolveSource(
            $request->source_type,
            $request->integer('source_id')
        );

        if (!$source['valid']) {
            return $this->sendError(
                $source['message'],
                [],
                422
            );
        }

        $lot = CoffeeLot::create([
            'coffee_season_id' =>
                $source['coffee_season_id'],

            'source_type' =>
                $request->source_type,

            'source_id' =>
                $request->integer('source_id'),

            'coffee_type' =>
                $source['coffee_type'],

            'initial_weight_kg' =>
                $source['weight_kg'],

            'current_weight_kg' =>
                $source['weight_kg'],

            'bag_count' =>
                $request->integer('bag_count')
                ?: null,

            'processing_stage' =>
                CoffeeLot::STAGE_RECEIVED,

            'status' =>
                CoffeeLot::STATUS_ACTIVE,

            'storage_location' =>
                $request->storage_location,

            'lot_date' =>
                $request->lot_date,

            'notes' =>
                $request->notes,

            'created_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $lot->fresh()->load(
                    $this->relations()
                )
            ),
            'Coffee lot created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        CoffeeLot $coffeeLot
    ): JsonResponse {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view this coffee lot.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $coffeeLot->load(
                    $this->relations()
                )
            ),
            'Coffee lot retrieved successfully.'
        );
    }

    public function update(
        UpdateCoffeeLotRequest $request,
        CoffeeLot $coffeeLot
    ): JsonResponse {
        if (
            $coffeeLot->status !==
            CoffeeLot::STATUS_ACTIVE
        ) {
            return $this->sendError(
                'Only active coffee lots can be updated.',
                [],
                422
            );
        }

        $coffeeLot->update([
            'bag_count' =>
                $request->integer('bag_count')
                ?: null,

            'storage_location' =>
                $request->storage_location,

            'notes' =>
                $request->notes,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $coffeeLot->fresh()->load(
                    $this->relations()
                )
            ),
            'Coffee lot updated successfully.'
        );
    }

    public function close(
        Request $request,
        CoffeeLot $coffeeLot
    ): JsonResponse {
        if (
            !in_array(
                $request->user()->role,
                ['admin', 'store'],
                true
            )
        ) {
            return $this->sendError(
                'You are not allowed to close coffee lots.',
                [],
                403
            );
        }

        if (
            $coffeeLot->status !==
            CoffeeLot::STATUS_ACTIVE
        ) {
            return $this->sendError(
                'Only active coffee lots can be closed.',
                [],
                422
            );
        }

        $coffeeLot->update([
            'status' =>
                CoffeeLot::STATUS_CLOSED,

            'closed_by' =>
                $request->user()->id,

            'closed_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $coffeeLot->fresh()->load(
                    $this->relations()
                )
            ),
            'Coffee lot closed successfully.'
        );
    }

    private function resolveSource(
        string $type,
        int $id
    ): array {
        if (
            $type ===
            CoffeeLot::SOURCE_FACTORY_RECEPTION
        ) {
            $reception =
                FactoryReception::query()
                    ->find($id);

            if (
                !$reception ||
                $reception->status !==
                    FactoryReception::STATUS_CONFIRMED
            ) {
                return [
                    'valid' => false,
                    'message' =>
                        'A confirmed factory reception is required.',
                ];
            }

            $coffeeTypes =
                CoffeePurchase::query()
                    ->where(
                        'agent_collection_id',
                        $reception
                            ->agent_collection_id
                    )
                    ->where(
                        'status',
                        CoffeePurchase::STATUS_APPROVED
                    )
                    ->distinct()
                    ->pluck('coffee_type');

            if ($coffeeTypes->count() !== 1) {
                return [
                    'valid' => false,
                    'message' =>
                        'The Agent Collection must contain one coffee type before a lot can be created.',
                ];
            }

            return [
                'valid' => true,
                'coffee_season_id' =>
                    $reception->coffee_season_id,
                'coffee_type' =>
                    $coffeeTypes->first(),
                'weight_kg' =>
                    (float)
                    $reception->factory_weight_kg,
            ];
        }

        $delivery =
            DirectFarmerDelivery::query()
                ->find($id);

        if (
            !$delivery ||
            $delivery->status !== 'confirmed' ||
            $delivery->payment_status !== 'paid'
        ) {
            return [
                'valid' => false,
                'message' =>
                    'A confirmed and paid direct farmer delivery is required.',
            ];
        }

        return [
            'valid' => true,
            'coffee_season_id' =>
                $delivery->coffee_season_id,
            'coffee_type' =>
                $delivery->coffee_type,
            'weight_kg' =>
                (float) $delivery->quantity_kg,
        ];
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
            'season:id,code,name,status',
            'creator:id,name',
            'updater:id,name',
            'closer:id,name',
        ];
    }

    private function data(CoffeeLot $lot): array
    {
        return [
            'id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'coffee_season_id' =>
                $lot->coffee_season_id,
            'source_type' =>
                $lot->source_type,
            'source_id' =>
                $lot->source_id,
            'coffee_type' =>
                $lot->coffee_type,
            'initial_weight_kg' =>
                $this->decimal(
                    $lot->initial_weight_kg
                ),
            'current_weight_kg' =>
                $this->decimal(
                    $lot->current_weight_kg
                ),
            'bag_count' =>
                $lot->bag_count,
            'processing_stage' =>
                $lot->processing_stage,
            'status' =>
                $lot->status,
            'storage_location' =>
                $lot->storage_location,
            'lot_date' =>
                $lot->lot_date
                    ?->format('Y-m-d'),
            'notes' =>
                $lot->notes,
            'season' =>
                $lot->season,
            'creator' =>
                $lot->creator,
            'updater' =>
                $lot->updater,
            'closer' =>
                $lot->closer,
            'closed_at' =>
                $lot->closed_at,
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
