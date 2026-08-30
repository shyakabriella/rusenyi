<?php

namespace App\Http\Controllers\API\Admin\Location;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Admin\Location\VillageRequest;
use App\Http\Resources\API\Location\VillageResource;
use App\Models\Village;
use App\Support\LocationPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VillageController extends BaseController
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
            'status' =>
                'nullable|in:active,inactive',
            'per_page' =>
                'nullable|integer|min:1|max:100',
        ]);

        $query = Village::query()
            ->with('cell')
            ->withCount('collectionPoints')

            ->when(
                $request->filled('cell_id'),
                fn ($query) =>
                    $query->where(
                        'cell_id',
                        $request->integer(
                            'cell_id'
                        )
                    )
            )

            ->when(
                $request->filled('sector_id'),
                fn ($query) =>
                    $query->whereHas(
                        'cell',
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
                        'cell.sector',
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
                        'cell.sector.district',
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
                fn ($query) =>
                    $query->where(
                        'name',
                        'like',
                        '%' .
                        trim(
                            (string) $request->search
                        ) .
                        '%'
                    )
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

        $villages = $query->paginate(
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
                $villages,
                VillageResource::class,
                $request
            ),
            'Villages retrieved successfully.'
        );
    }

    public function store(
        VillageRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $data['is_active'] =
            $data['is_active'] ?? true;

        $village = Village::create($data);

        $village->load('cell')
            ->loadCount('collectionPoints');

        return $this->sendResponse(
            new VillageResource($village),
            'Village created successfully.',
            201
        );
    }

    public function show(
        Village $village
    ): JsonResponse {
        $village->load('cell')
            ->loadCount('collectionPoints');

        return $this->sendResponse(
            new VillageResource($village),
            'Village retrieved successfully.'
        );
    }

    public function update(
        VillageRequest $request,
        Village $village
    ): JsonResponse {
        $village->update(
            $request->validated()
        );

        $village = $village->fresh()
            ->load('cell');

        $village->loadCount(
            'collectionPoints'
        );

        return $this->sendResponse(
            new VillageResource($village),
            'Village updated successfully.'
        );
    }

    public function updateStatus(
        Request $request,
        Village $village
    ): JsonResponse {
        $request->validate([
            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $village->update([
            'is_active' =>
                $request->boolean('is_active'),
        ]);

        return $this->sendResponse(
            new VillageResource(
                $village->fresh()->load(
                    'cell'
                )
            ),
            'Village status updated successfully.'
        );
    }
}
