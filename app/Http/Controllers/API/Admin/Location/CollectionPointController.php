<?php

namespace App\Http\Controllers\API\Admin\Location;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Admin\Location\CollectionPointRequest;
use App\Http\Resources\API\Location\CollectionPointResource;
use App\Models\CollectionPoint;
use App\Support\LocationPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionPointController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' =>
                'nullable|string|max:150',

            'province_id' =>
                'nullable|integer|exists:provinces,id',

            'district_id' =>
                'nullable|integer|exists:districts,id',

            'sector_id' =>
                'nullable|integer|exists:sectors,id',

            'cell_id' =>
                'nullable|integer|exists:cells,id',

            'village_id' =>
                'nullable|integer|exists:villages,id',

            'status' =>
                'nullable|in:active,inactive',

            'per_page' =>
                'nullable|integer|min:1|max:100',
        ]);

        $query = CollectionPoint::query()
            ->with([
                'village.cell.sector.district.province',
            ])
            ->withCount('agents')

            ->when(
                $request->filled('village_id'),
                fn ($query) =>
                    $query->where(
                        'village_id',
                        $request->integer(
                            'village_id'
                        )
                    )
            )

            ->when(
                $request->filled('cell_id'),
                fn ($query) =>
                    $query->whereHas(
                        'village',
                        fn ($village) =>
                            $village->where(
                                'cell_id',
                                $request->integer(
                                    'cell_id'
                                )
                            )
                    )
            )

            ->when(
                $request->filled('sector_id'),
                fn ($query) =>
                    $query->whereHas(
                        'village.cell',
                        fn ($cell) =>
                            $cell->where(
                                'sector_id',
                                $request->integer(
                                    'sector_id'
                                )
                            )
                    )
            )

            ->when(
                $request->filled('district_id'),
                fn ($query) =>
                    $query->whereHas(
                        'village.cell.sector',
                        fn ($sector) =>
                            $sector->where(
                                'district_id',
                                $request->integer(
                                    'district_id'
                                )
                            )
                    )
            )

            ->when(
                $request->filled('province_id'),
                fn ($query) =>
                    $query->whereHas(
                        'village.cell.sector.district',
                        fn ($district) =>
                            $district->where(
                                'province_id',
                                $request->integer(
                                    'province_id'
                                )
                            )
                    )
            )

            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search =
                        trim(
                            (string) $request->search
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
                                )
                                ->orWhere(
                                    'description',
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
                        'is_active',
                        $request->status === 'active'
                    )
            )

            ->orderBy('name');

        $points = $query->paginate(
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
            LocationPagination::make(
                $points,
                CollectionPointResource::class,
                $request
            ),
            'Collection points retrieved successfully.'
        );
    }

    public function store(
        CollectionPointRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $data['is_active'] =
            $data['is_active'] ?? true;

        $point =
            CollectionPoint::create(
                $data
            );

        $point->load([
            'village.cell.sector.district.province',
        ]);

        $point->loadCount('agents');

        return $this->sendResponse(
            new CollectionPointResource(
                $point
            ),
            'Collection point created successfully.',
            201
        );
    }

    public function show(
        CollectionPoint $collectionPoint
    ): JsonResponse {
        $collectionPoint->load([
            'village.cell.sector.district.province',
            'agents',
        ]);

        $collectionPoint->loadCount(
            'agents'
        );

        return $this->sendResponse(
            new CollectionPointResource(
                $collectionPoint
            ),
            'Collection point retrieved successfully.'
        );
    }

    public function update(
        CollectionPointRequest $request,
        CollectionPoint $collectionPoint
    ): JsonResponse {
        $collectionPoint->update(
            $request->validated()
        );

        $collectionPoint =
            $collectionPoint
                ->fresh()
                ->load([
                    'village.cell.sector.district.province',
                ]);

        $collectionPoint->loadCount(
            'agents'
        );

        return $this->sendResponse(
            new CollectionPointResource(
                $collectionPoint
            ),
            'Collection point updated successfully.'
        );
    }

    public function updateStatus(
        Request $request,
        CollectionPoint $collectionPoint
    ): JsonResponse {
        $request->validate([
            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $collectionPoint->update([
            'is_active' =>
                $request->boolean('is_active'),
        ]);

        return $this->sendResponse(
            new CollectionPointResource(
                $collectionPoint
                    ->fresh()
                    ->load([
                        'village.cell.sector.district.province',
                    ])
            ),
            'Collection point status updated successfully.'
        );
    }
}
