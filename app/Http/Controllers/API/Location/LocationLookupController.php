<?php

namespace App\Http\Controllers\API\Location;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\API\Location\CellResource;
use App\Http\Resources\API\Location\CollectionPointResource;
use App\Http\Resources\API\Location\DistrictResource;
use App\Http\Resources\API\Location\ProvinceResource;
use App\Http\Resources\API\Location\SectorResource;
use App\Http\Resources\API\Location\VillageResource;
use App\Models\Cell;
use App\Models\CollectionPoint;
use App\Models\District;
use App\Models\Province;
use App\Models\Sector;
use App\Models\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationLookupController extends BaseController
{
    public function provinces(
        Request $request
    ): JsonResponse {
        $items = Province::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->sendResponse(
            ProvinceResource::collection(
                $items
            )->resolve($request),
            'Active provinces retrieved successfully.'
        );
    }

    public function districts(
        Request $request
    ): JsonResponse {
        $request->validate([
            'province_id' =>
                'required|integer|exists:provinces,id',
        ]);

        $items = District::query()
            ->with('province')
            ->where(
                'province_id',
                $request->integer(
                    'province_id'
                )
            )
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->sendResponse(
            DistrictResource::collection(
                $items
            )->resolve($request),
            'Active districts retrieved successfully.'
        );
    }

    public function sectors(
        Request $request
    ): JsonResponse {
        $request->validate([
            'district_id' =>
                'required|integer|exists:districts,id',
        ]);

        $items = Sector::query()
            ->with('district')
            ->where(
                'district_id',
                $request->integer(
                    'district_id'
                )
            )
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->sendResponse(
            SectorResource::collection(
                $items
            )->resolve($request),
            'Active sectors retrieved successfully.'
        );
    }

    public function cells(
        Request $request
    ): JsonResponse {
        $request->validate([
            'sector_id' =>
                'required|integer|exists:sectors,id',
        ]);

        $items = Cell::query()
            ->with('sector')
            ->where(
                'sector_id',
                $request->integer(
                    'sector_id'
                )
            )
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->sendResponse(
            CellResource::collection(
                $items
            )->resolve($request),
            'Active cells retrieved successfully.'
        );
    }

    public function villages(
        Request $request
    ): JsonResponse {
        $request->validate([
            'cell_id' =>
                'required|integer|exists:cells,id',
        ]);

        $items = Village::query()
            ->with('cell')
            ->where(
                'cell_id',
                $request->integer(
                    'cell_id'
                )
            )
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->sendResponse(
            VillageResource::collection(
                $items
            )->resolve($request),
            'Active villages retrieved successfully.'
        );
    }

    public function collectionPoints(
        Request $request
    ): JsonResponse {
        $request->validate([
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

            'search' =>
                'nullable|string|max:150',
        ]);

        $query =
            CollectionPoint::query()
                ->with([
                    'village.cell.sector.district.province',
                ])
                ->where(
                    'is_active',
                    true
                );

        if (
            $request->filled(
                'village_id'
            )
        ) {
            $query->where(
                'village_id',
                $request->integer(
                    'village_id'
                )
            );
        }

        if (
            $request->filled(
                'cell_id'
            )
        ) {
            $query->whereHas(
                'village',
                fn ($village) =>
                    $village->where(
                        'cell_id',
                        $request->integer(
                            'cell_id'
                        )
                    )
            );
        }

        if (
            $request->filled(
                'sector_id'
            )
        ) {
            $query->whereHas(
                'village.cell',
                fn ($cell) =>
                    $cell->where(
                        'sector_id',
                        $request->integer(
                            'sector_id'
                        )
                    )
            );
        }

        if (
            $request->filled(
                'district_id'
            )
        ) {
            $query->whereHas(
                'village.cell.sector',
                fn ($sector) =>
                    $sector->where(
                        'district_id',
                        $request->integer(
                            'district_id'
                        )
                    )
            );
        }

        if (
            $request->filled(
                'province_id'
            )
        ) {
            $query->whereHas(
                'village.cell.sector.district',
                fn ($district) =>
                    $district->where(
                        'province_id',
                        $request->integer(
                            'province_id'
                        )
                    )
            );
        }

        if (
            $request->filled('search')
        ) {
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
                        );
                }
            );
        }

        $items = $query
            ->orderBy('name')
            ->get();

        return $this->sendResponse(
            CollectionPointResource::collection(
                $items
            )->resolve($request),
            'Active collection points retrieved successfully.'
        );
    }
}
