<?php

namespace App\Http\Controllers\API\Admin\Location;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Admin\Location\DistrictRequest;
use App\Http\Resources\API\Location\DistrictResource;
use App\Models\District;
use App\Support\LocationPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistrictController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:150',
            'province_id' =>
                'nullable|integer|exists:provinces,id',
            'status' =>
                'nullable|in:active,inactive',
            'per_page' =>
                'nullable|integer|min:1|max:100',
        ]);

        $query = District::query()
            ->with('province')
            ->withCount('sectors')
            ->when(
                $request->filled('province_id'),
                fn ($query) =>
                    $query->where(
                        'province_id',
                        $request->integer(
                            'province_id'
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

        $districts = $query->paginate(
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
                $districts,
                DistrictResource::class,
                $request
            ),
            'Districts retrieved successfully.'
        );
    }

    public function store(
        DistrictRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $data['is_active'] =
            $data['is_active'] ?? true;

        $district = District::create($data);

        $district->load('province')
            ->loadCount('sectors');

        return $this->sendResponse(
            new DistrictResource($district),
            'District created successfully.',
            201
        );
    }

    public function show(
        District $district
    ): JsonResponse {
        $district->load('province')
            ->loadCount('sectors');

        return $this->sendResponse(
            new DistrictResource($district),
            'District retrieved successfully.'
        );
    }

    public function update(
        DistrictRequest $request,
        District $district
    ): JsonResponse {
        $district->update(
            $request->validated()
        );

        $district = $district->fresh()
            ->load('province');

        $district->loadCount('sectors');

        return $this->sendResponse(
            new DistrictResource($district),
            'District updated successfully.'
        );
    }

    public function updateStatus(
        Request $request,
        District $district
    ): JsonResponse {
        $request->validate([
            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $district->update([
            'is_active' =>
                $request->boolean('is_active'),
        ]);

        return $this->sendResponse(
            new DistrictResource(
                $district->fresh()->load(
                    'province'
                )
            ),
            'District status updated successfully.'
        );
    }
}
