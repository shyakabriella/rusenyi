<?php

namespace App\Http\Controllers\API\Admin\CoffeePrice;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Admin\CoffeePrice\StoreCoffeePriceRequest;
use App\Http\Requests\API\Admin\CoffeePrice\UpdateCoffeePriceRequest;
use App\Http\Resources\API\CoffeePrice\CoffeePriceResource;
use App\Models\CoffeePrice;
use App\Models\CoffeeSeason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoffeePriceController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],

            'coffee_season_id' => [
                'nullable',
                'integer',
                'exists:coffee_seasons,id',
            ],

            'coffee_type' => [
                'nullable',
                'string',
                'in:cherry,parchment,green_coffee',
            ],

            'status' => [
                'nullable',
                'in:draft,active,inactive',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = CoffeePrice::query()
            ->with([
                'season',
                'creator',
                'activator',
                'deactivator',
            ])

            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = trim(
                        (string) $request->search
                    );

                    $query->where(
                        function ($query) use ($search) {
                            $query
                                ->where(
                                    'code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'coffee_type',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhereHas(
                                    'season',
                                    function ($season) use ($search) {
                                        $season
                                            ->where(
                                                'name',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhere(
                                                'code',
                                                'like',
                                                "%{$search}%"
                                            );
                                    }
                                );
                        }
                    );
                }
            )

            ->when(
                $request->filled('coffee_season_id'),
                fn ($query) =>
                    $query->where(
                        'coffee_season_id',
                        $request->integer(
                            'coffee_season_id'
                        )
                    )
            )

            ->when(
                $request->filled('coffee_type'),
                fn ($query) =>
                    $query->where(
                        'coffee_type',
                        $request->coffee_type
                    )
            )

            ->when(
                $request->filled('status'),
                fn ($query) =>
                    $query->where(
                        'status',
                        $request->status
                    )
            )

            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        $prices = $query->paginate(
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

        return $this->sendResponse(
            [
                'items' =>
                    CoffeePriceResource::collection(
                        $prices->getCollection()
                    )->resolve($request),

                'pagination' => [
                    'current_page' =>
                        $prices->currentPage(),

                    'last_page' =>
                        $prices->lastPage(),

                    'per_page' =>
                        $prices->perPage(),

                    'total' =>
                        $prices->total(),

                    'from' =>
                        $prices->firstItem(),

                    'to' =>
                        $prices->lastItem(),
                ],
            ],
            'Coffee prices retrieved successfully.'
        );
    }

    public function store(
        StoreCoffeePriceRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $price = DB::transaction(
            function () use (
                $request,
                $data
            ) {
                $season = CoffeeSeason::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $data['coffee_season_id']
                    );

                $data['code'] =
                    $this->generatePriceCode(
                        $season
                    );

                $data['currency'] = 'RWF';

                $data['status'] =
                    CoffeePrice::STATUS_DRAFT;

                $data['created_by'] =
                    $request->user()->id;

                return CoffeePrice::create(
                    $data
                );
            }
        );

        $price->load([
            'season',
            'creator',
            'activator',
            'deactivator',
        ]);

        return $this->sendResponse(
            new CoffeePriceResource($price),
            'Coffee price created successfully.',
            201
        );
    }

    public function show(
        CoffeePrice $coffeePrice
    ): JsonResponse {
        $coffeePrice->load([
            'season',
            'creator',
            'activator',
            'deactivator',
        ]);

        return $this->sendResponse(
            new CoffeePriceResource(
                $coffeePrice
            ),
            'Coffee price retrieved successfully.'
        );
    }

    public function update(
        UpdateCoffeePriceRequest $request,
        CoffeePrice $coffeePrice
    ): JsonResponse {
        if (
            $coffeePrice->status !==
            CoffeePrice::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft coffee prices can be edited.',
                [
                    'coffee_price' =>
                        'Active or inactive price records are preserved as price history.',
                ],
                422
            );
        }

        $coffeePrice->update(
            $request->validated()
        );

        $coffeePrice = $coffeePrice
            ->fresh()
            ->load([
                'season',
                'creator',
                'activator',
                'deactivator',
            ]);

        return $this->sendResponse(
            new CoffeePriceResource(
                $coffeePrice
            ),
            'Coffee price updated successfully.'
        );
    }

    public function activate(
        Request $request,
        CoffeePrice $coffeePrice
    ): JsonResponse {
        if (
            $coffeePrice->status ===
            CoffeePrice::STATUS_ACTIVE
        ) {
            return $this->sendResponse(
                new CoffeePriceResource(
                    $coffeePrice->load([
                        'season',
                        'creator',
                        'activator',
                        'deactivator',
                    ])
                ),
                'Coffee price is already active.'
            );
        }

        if (
            $coffeePrice->status ===
            CoffeePrice::STATUS_INACTIVE
        ) {
            return $this->sendError(
                'Inactive coffee price cannot be activated again.',
                [
                    'coffee_price' =>
                        'Create a new Draft price to preserve price history.',
                ],
                422
            );
        }

        $result = DB::transaction(
            function () use (
                $request,
                $coffeePrice
            ) {
                $season = CoffeeSeason::query()
                    ->lockForUpdate()
                    ->find(
                        $coffeePrice
                            ->coffee_season_id
                    );

                if (!$season) {
                    return [
                        'error' =>
                            'season_missing',
                    ];
                }

                if (
                    $season->status !==
                    CoffeeSeason::STATUS_ACTIVE
                ) {
                    return [
                        'error' =>
                            'season_not_active',
                    ];
                }

                $existing =
                    CoffeePrice::query()
                        ->where(
                            'coffee_season_id',
                            $coffeePrice
                                ->coffee_season_id
                        )
                        ->where(
                            'coffee_type',
                            $coffeePrice
                                ->coffee_type
                        )
                        ->where(
                            'status',
                            CoffeePrice::STATUS_ACTIVE
                        )
                        ->where(
                            'id',
                            '!=',
                            $coffeePrice->id
                        )
                        ->lockForUpdate()
                        ->first();

                if ($existing) {
                    return [
                        'error' =>
                            'active_price_exists',

                        'price' =>
                            $existing,
                    ];
                }

                $coffeePrice->update([
                    'status' =>
                        CoffeePrice::STATUS_ACTIVE,

                    'activated_by' =>
                        $request->user()->id,

                    'activated_at' =>
                        now(),

                    'deactivated_by' =>
                        null,

                    'deactivated_at' =>
                        null,
                ]);

                return [
                    'price' =>
                        $coffeePrice
                            ->fresh()
                            ->load([
                                'season',
                                'creator',
                                'activator',
                                'deactivator',
                            ]),
                ];
            }
        );

        if (
            ($result['error'] ?? null) ===
            'season_missing'
        ) {
            return $this->sendError(
                'Coffee season not found.',
                [],
                422
            );
        }

        if (
            ($result['error'] ?? null) ===
            'season_not_active'
        ) {
            return $this->sendError(
                'Coffee price cannot be activated.',
                [
                    'coffee_season' =>
                        'Only prices belonging to the active coffee season can be activated.',
                ],
                422
            );
        }

        if (
            ($result['error'] ?? null) ===
            'active_price_exists'
        ) {
            $activePrice =
                $result['price'];

            return $this->sendError(
                'Another active price already exists for this coffee type.',
                [
                    'active_price' => [
                        'id' =>
                            $activePrice->id,

                        'code' =>
                            $activePrice->code,

                        'coffee_type' =>
                            $activePrice
                                ->coffee_type,

                        'price_per_kg' =>
                            (float)
                            $activePrice
                                ->price_per_kg,
                    ],
                ],
                422
            );
        }

        return $this->sendResponse(
            new CoffeePriceResource(
                $result['price']
            ),
            'Coffee price activated successfully.'
        );
    }

    public function deactivate(
        Request $request,
        CoffeePrice $coffeePrice
    ): JsonResponse {
        if (
            $coffeePrice->status !==
            CoffeePrice::STATUS_ACTIVE
        ) {
            return $this->sendError(
                'Only an active coffee price can be deactivated.',
                [],
                422
            );
        }

        $coffeePrice->update([
            'status' =>
                CoffeePrice::STATUS_INACTIVE,

            'deactivated_by' =>
                $request->user()->id,

            'deactivated_at' =>
                now(),
        ]);

        return $this->sendResponse(
            new CoffeePriceResource(
                $coffeePrice
                    ->fresh()
                    ->load([
                        'season',
                        'creator',
                        'activator',
                        'deactivator',
                    ])
            ),
            'Coffee price deactivated successfully.'
        );
    }

    private function generatePriceCode(
        CoffeeSeason $season
    ): string {
        $year = $season
            ->start_date
            ->format('Y');

        $prefix =
            "PRICE-{$year}-";

        $lastCode =
            CoffeePrice::query()
                ->where(
                    'code',
                    'like',
                    $prefix . '%'
                )
                ->orderByDesc('code')
                ->value('code');

        $nextNumber = 1;

        if ($lastCode) {
            $number = (int) substr(
                $lastCode,
                strlen($prefix)
            );

            $nextNumber =
                $number + 1;
        }

        do {
            $code =
                $prefix .
                str_pad(
                    (string) $nextNumber,
                    3,
                    '0',
                    STR_PAD_LEFT
                );

            $exists =
                CoffeePrice::query()
                    ->where(
                        'code',
                        $code
                    )
                    ->exists();

            $nextNumber++;
        } while ($exists);

        return $code;
    }
}
