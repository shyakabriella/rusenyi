<?php

namespace App\Http\Controllers\API\Admin\CoffeeSeason;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Admin\CoffeeSeason\StoreCoffeeSeasonRequest;
use App\Http\Requests\API\Admin\CoffeeSeason\UpdateCoffeeSeasonRequest;
use App\Http\Resources\API\CoffeeSeason\CoffeeSeasonResource;
use App\Models\CoffeeSeason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoffeeSeasonController extends BaseController
{
    public function index(
        Request $request
    ): JsonResponse {
        $request->validate([
            'search' =>
                'nullable|string|max:150',

            'status' =>
                'nullable|in:draft,active,closed',

            'year' =>
                'nullable|integer|min:2000|max:2200',

            'per_page' =>
                'nullable|integer|min:1|max:100',
        ]);

        $query = CoffeeSeason::query()
            ->with([
                'creator',
                'activator',
                'closer',
            ])

            ->when(
                $request->filled('search'),
                function ($query) use (
                    $request
                ) {
                    $search = trim(
                        (string)
                        $request->search
                    );

                    $query->where(
                        function ($query) use (
                            $search
                        ) {
                            $query
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
            )

            ->when(
                $request->filled('status'),
                fn ($query) =>
                    $query->where(
                        'status',
                        $request->status
                    )
            )

            ->when(
                $request->filled('year'),
                fn ($query) =>
                    $query->whereYear(
                        'start_date',
                        $request->integer(
                            'year'
                        )
                    )
            )

            ->orderByDesc('start_date')
            ->orderByDesc('id');

        $seasons = $query->paginate(
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
                    CoffeeSeasonResource::collection(
                        $seasons
                            ->getCollection()
                    )->resolve($request),

                'pagination' => [
                    'current_page' =>
                        $seasons
                            ->currentPage(),

                    'last_page' =>
                        $seasons
                            ->lastPage(),

                    'per_page' =>
                        $seasons
                            ->perPage(),

                    'total' =>
                        $seasons->total(),

                    'from' =>
                        $seasons
                            ->firstItem(),

                    'to' =>
                        $seasons
                            ->lastItem(),
                ],
            ],
            'Coffee seasons retrieved successfully.'
        );
    }

    public function store(
        StoreCoffeeSeasonRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $data['code'] =
            $this->generateSeasonCode(
                $data['start_date']
            );

        $data['status'] =
            CoffeeSeason::STATUS_DRAFT;

        $data['created_by'] =
            $request->user()->id;

        $season =
            CoffeeSeason::create(
                $data
            );

        $season->load('creator');

        return $this->sendResponse(
            new CoffeeSeasonResource(
                $season
            ),
            'Coffee season created successfully.',
            201
        );
    }

    public function show(
        CoffeeSeason $coffeeSeason
    ): JsonResponse {
        $coffeeSeason->load([
            'creator',
            'activator',
            'closer',
        ]);

        return $this->sendResponse(
            new CoffeeSeasonResource(
                $coffeeSeason
            ),
            'Coffee season retrieved successfully.'
        );
    }

    public function update(
        UpdateCoffeeSeasonRequest $request,
        CoffeeSeason $coffeeSeason
    ): JsonResponse {
        if (
            $coffeeSeason->status ===
            CoffeeSeason::STATUS_CLOSED
        ) {
            return $this->sendError(
                'Closed coffee season cannot be edited.',
                [
                    'season' =>
                        'Reopen the season before editing it.',
                ],
                422
            );
        }

        $coffeeSeason->update(
            $request->validated()
        );

        $coffeeSeason =
            $coffeeSeason
                ->fresh()
                ->load([
                    'creator',
                    'activator',
                    'closer',
                ]);

        return $this->sendResponse(
            new CoffeeSeasonResource(
                $coffeeSeason
            ),
            'Coffee season updated successfully.'
        );
    }

    public function activate(
        Request $request,
        CoffeeSeason $coffeeSeason
    ): JsonResponse {
        if (
            $coffeeSeason->status ===
            CoffeeSeason::STATUS_ACTIVE
        ) {
            return $this->sendResponse(
                new CoffeeSeasonResource(
                    $coffeeSeason->load([
                        'creator',
                        'activator',
                        'closer',
                    ])
                ),
                'Coffee season is already active.'
            );
        }

        if (
            $coffeeSeason->status ===
            CoffeeSeason::STATUS_CLOSED
        ) {
            return $this->sendError(
                'Closed coffee season cannot be activated directly.',
                [
                    'season' =>
                        'Use the reopen action for a closed season.',
                ],
                422
            );
        }

        $result = DB::transaction(
            function () use (
                $request,
                $coffeeSeason
            ) {
                $activeSeason =
                    CoffeeSeason::query()
                        ->where(
                            'status',
                            CoffeeSeason::STATUS_ACTIVE
                        )
                        ->whereKeyNot(
                            $coffeeSeason->id
                        )
                        ->first();

                if ($activeSeason) {
                    return [
                        'error' =>
                            $activeSeason,
                    ];
                }

                $coffeeSeason->update([
                    'status' =>
                        CoffeeSeason::STATUS_ACTIVE,

                    'activated_by' =>
                        $request->user()->id,

                    'activated_at' =>
                        now(),

                    'closed_by' =>
                        null,

                    'closed_at' =>
                        null,
                ]);

                return [
                    'season' =>
                        $coffeeSeason
                            ->fresh()
                            ->load([
                                'creator',
                                'activator',
                                'closer',
                            ]),
                ];
            }
        );

        if (
            isset($result['error'])
        ) {
            $active =
                $result['error'];

            return $this->sendError(
                'Another coffee season is already active.',
                [
                    'active_season' => [
                        'id' =>
                            $active->id,

                        'name' =>
                            $active->name,

                        'code' =>
                            $active->code,
                    ],
                ],
                422
            );
        }

        return $this->sendResponse(
            new CoffeeSeasonResource(
                $result['season']
            ),
            'Coffee season activated successfully.'
        );
    }

    public function close(
        Request $request,
        CoffeeSeason $coffeeSeason
    ): JsonResponse {
        if (
            $coffeeSeason->status !==
            CoffeeSeason::STATUS_ACTIVE
        ) {
            return $this->sendError(
                'Only an active coffee season can be closed.',
                [
                    'season' =>
                        'Activate the season before closing it.',
                ],
                422
            );
        }

        $coffeeSeason->update([
            'status' =>
                CoffeeSeason::STATUS_CLOSED,

            'closed_by' =>
                $request->user()->id,

            'closed_at' =>
                now(),
        ]);

        return $this->sendResponse(
            new CoffeeSeasonResource(
                $coffeeSeason
                    ->fresh()
                    ->load([
                        'creator',
                        'activator',
                        'closer',
                    ])
            ),
            'Coffee season closed successfully.'
        );
    }

    public function reopen(
        Request $request,
        CoffeeSeason $coffeeSeason
    ): JsonResponse {
        if (
            $coffeeSeason->status !==
            CoffeeSeason::STATUS_CLOSED
        ) {
            return $this->sendError(
                'Only a closed coffee season can be reopened.',
                [],
                422
            );
        }

        $activeSeason =
            CoffeeSeason::query()
                ->where(
                    'status',
                    CoffeeSeason::STATUS_ACTIVE
                )
                ->first();

        if ($activeSeason) {
            return $this->sendError(
                'Another coffee season is already active.',
                [
                    'active_season' => [
                        'id' =>
                            $activeSeason->id,

                        'name' =>
                            $activeSeason->name,
                    ],
                ],
                422
            );
        }

        $coffeeSeason->update([
            'status' =>
                CoffeeSeason::STATUS_ACTIVE,

            'activated_by' =>
                $request->user()->id,

            'activated_at' =>
                now(),

            'closed_by' =>
                null,

            'closed_at' =>
                null,
        ]);

        return $this->sendResponse(
            new CoffeeSeasonResource(
                $coffeeSeason
                    ->fresh()
                    ->load([
                        'creator',
                        'activator',
                        'closer',
                    ])
            ),
            'Coffee season reopened successfully.'
        );
    }
    private function generateSeasonCode(
        string $startDate
    ): string {
        $year = date(
            'Y',
            strtotime($startDate)
        );

        $prefix = "CS-{$year}-";

        $lastCode = CoffeeSeason::query()
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
            $code = $prefix .
                str_pad(
                    (string) $nextNumber,
                    3,
                    '0',
                    STR_PAD_LEFT
                );

            $exists =
                CoffeeSeason::query()
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
