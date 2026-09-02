<?php

namespace App\Http\Controllers\API\Farmer;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\API\Farmer\FarmerResource;
use App\Models\Farmer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmerLookupController extends BaseController
{
    public function __invoke(
        Request $request
    ): JsonResponse {
        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],

            'village_id' => [
                'nullable',
                'integer',
                'exists:villages,id',
            ],

            'collection_point_id' => [
                'nullable',
                'integer',
                'exists:collection_points,id',
            ],
        ]);

        $farmers =
            Farmer::query()
                ->with([
                    'village.cell.sector.district.province',
                    'collectionPoint',
                ])
                ->active()

                ->when(
                    $request->filled(
                        'search'
                    ),
                    function (
                        $query
                    ) use ($request) {
                        $search =
                            trim(
                                (string)
                                $request->search
                            );

                        $query->where(
                            function (
                                $query
                            ) use ($search) {
                                $query
                                    ->where(
                                        'farmer_code',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'full_name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'phone',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'national_id',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        );
                    }
                )

                ->when(
                    $request->filled(
                        'village_id'
                    ),
                    fn ($query) =>
                        $query->where(
                            'village_id',
                            $request->integer(
                                'village_id'
                            )
                        )
                )

                ->when(
                    $request->filled(
                        'collection_point_id'
                    ),
                    fn ($query) =>
                        $query->where(
                            'collection_point_id',
                            $request->integer(
                                'collection_point_id'
                            )
                        )
                )

                ->orderBy(
                    'full_name'
                )
                ->limit(25)
                ->get();

        return $this->sendResponse(
            [
                'items' =>
                    FarmerResource::collection(
                        $farmers
                    )->resolve(
                        $request
                    ),
            ],
            'Active farmers retrieved successfully.'
        );
    }
}
