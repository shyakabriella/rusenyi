<?php

namespace App\Http\Controllers\API\Admin\Location;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Admin\Location\ProvinceRequest;
use App\Http\Resources\API\Location\ProvinceResource;
use App\Models\Province;
use App\Support\LocationPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProvinceController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:150',
            'status' => 'nullable|in:active,inactive',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Province::query()
            ->withCount('districts')
            ->when(
                $request->filled('search'),
                fn ($query) =>
                    $query->where(
                        'name',
                        'like',
                        '%' . trim((string) $request->search) . '%'
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

        $provinces = $query->paginate(
            min(
                max(
                    (int) $request->get('per_page', 20),
                    1
                ),
                100
            )
        );

        return $this->sendResponse(
            LocationPagination::make(
                $provinces,
                ProvinceResource::class,
                $request
            ),
            'Provinces retrieved successfully.'
        );
    }

    public function store(
        ProvinceRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $data['is_active'] =
            $data['is_active'] ?? true;

        $province = Province::create($data);

        return $this->sendResponse(
            new ProvinceResource($province),
            'Province created successfully.',
            201
        );
    }

    public function show(
        Province $province
    ): JsonResponse {
        $province->loadCount('districts');

        return $this->sendResponse(
            new ProvinceResource($province),
            'Province retrieved successfully.'
        );
    }

    public function update(
        ProvinceRequest $request,
        Province $province
    ): JsonResponse {
        $province->update(
            $request->validated()
        );

        $province->loadCount('districts');

        return $this->sendResponse(
            new ProvinceResource(
                $province->fresh()
            ),
            'Province updated successfully.'
        );
    }

    public function updateStatus(
        Request $request,
        Province $province
    ): JsonResponse {
        $request->validate([
            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $province->update([
            'is_active' =>
                $request->boolean('is_active'),
        ]);

        return $this->sendResponse(
            new ProvinceResource(
                $province->fresh()
            ),
            'Province status updated successfully.'
        );
    }
}
