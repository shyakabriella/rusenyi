<?php

namespace App\Http\Controllers\API\Admin\Location;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Admin\Location\SectorRequest;
use App\Http\Resources\API\Location\SectorResource;
use App\Models\Sector;
use App\Support\LocationPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectorController extends BaseController
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
            'status' =>
                'nullable|in:active,inactive',
            'per_page' =>
                'nullable|integer|min:1|max:100',
        ]);

        $query = Sector::query()
            ->with('district')
            ->withCount('cells')

            ->when(
                $request->filled('district_id'),
                fn ($query) =>
                    $query->where(
                        'district_id',
                        $request->integer(
                            'district_id'
                        )
                    )
            )

            ->when(
                $request->filled('province_id'),
                fn ($query) =>
                    $query->whereHas(
                        'district',
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

        $sectors = $query->paginate(
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
                $sectors,
                SectorResource::class,
                $request
            ),
            'Sectors retrieved successfully.'
        );
    }

    public function store(
        SectorRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $data['is_active'] =
            $data['is_active'] ?? true;

        $sector = Sector::create($data);

        $sector->load('district')
            ->loadCount('cells');

        return $this->sendResponse(
            new SectorResource($sector),
            'Sector created successfully.',
            201
        );
    }

    public function show(
        Sector $sector
    ): JsonResponse {
        $sector->load('district')
            ->loadCount('cells');

        return $this->sendResponse(
            new SectorResource($sector),
            'Sector retrieved successfully.'
        );
    }

    public function update(
        SectorRequest $request,
        Sector $sector
    ): JsonResponse {
        $sector->update(
            $request->validated()
        );

        $sector = $sector->fresh()
            ->load('district');

        $sector->loadCount('cells');

        return $this->sendResponse(
            new SectorResource($sector),
            'Sector updated successfully.'
        );
    }

    public function updateStatus(
        Request $request,
        Sector $sector
    ): JsonResponse {
        $request->validate([
            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $sector->update([
            'is_active' =>
                $request->boolean('is_active'),
        ]);

        return $this->sendResponse(
            new SectorResource(
                $sector->fresh()->load(
                    'district'
                )
            ),
            'Sector status updated successfully.'
        );
    }
}
