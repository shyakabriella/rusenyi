<?php

namespace App\Http\Controllers\API\Admin\Location;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Admin\Location\CellRequest;
use App\Http\Resources\API\Location\CellResource;
use App\Models\Cell;
use App\Support\LocationPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CellController extends BaseController
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
            'status' =>
                'nullable|in:active,inactive',
            'per_page' =>
                'nullable|integer|min:1|max:100',
        ]);

        $query = Cell::query()
            ->with('sector')
            ->withCount('villages')

            ->when(
                $request->filled('sector_id'),
                fn ($query) =>
                    $query->where(
                        'sector_id',
                        $request->integer(
                            'sector_id'
                        )
                    )
            )

            ->when(
                $request->filled('district_id'),
                fn ($query) =>
                    $query->whereHas(
                        'sector',
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
                        'sector.district',
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

        $cells = $query->paginate(
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
                $cells,
                CellResource::class,
                $request
            ),
            'Cells retrieved successfully.'
        );
    }

    public function store(
        CellRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $data['is_active'] =
            $data['is_active'] ?? true;

        $cell = Cell::create($data);

        $cell->load('sector')
            ->loadCount('villages');

        return $this->sendResponse(
            new CellResource($cell),
            'Cell created successfully.',
            201
        );
    }

    public function show(
        Cell $cell
    ): JsonResponse {
        $cell->load('sector')
            ->loadCount('villages');

        return $this->sendResponse(
            new CellResource($cell),
            'Cell retrieved successfully.'
        );
    }

    public function update(
        CellRequest $request,
        Cell $cell
    ): JsonResponse {
        $cell->update(
            $request->validated()
        );

        $cell = $cell->fresh()
            ->load('sector');

        $cell->loadCount('villages');

        return $this->sendResponse(
            new CellResource($cell),
            'Cell updated successfully.'
        );
    }

    public function updateStatus(
        Request $request,
        Cell $cell
    ): JsonResponse {
        $request->validate([
            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $cell->update([
            'is_active' =>
                $request->boolean('is_active'),
        ]);

        return $this->sendResponse(
            new CellResource(
                $cell->fresh()->load(
                    'sector'
                )
            ),
            'Cell status updated successfully.'
        );
    }
}
