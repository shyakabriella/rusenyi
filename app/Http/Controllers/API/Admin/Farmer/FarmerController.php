<?php

namespace App\Http\Controllers\API\Admin\Farmer;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Admin\Farmer\StoreFarmerRequest;
use App\Http\Requests\API\Admin\Farmer\UpdateFarmerRequest;
use App\Http\Resources\API\Farmer\FarmerResource;
use App\Models\Farmer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FarmerController extends BaseController
{
    private array $relations = [
        'village.cell.sector.district.province',
        'collectionPoint',
        'creator',
        'updater',
        'deactivator',
        'reactivator',
    ];

    public function index(
        Request $request
    ): JsonResponse {
        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],

            'status' => [
                'nullable',
                'in:active,inactive',
            ],

            'province_id' => [
                'nullable',
                'integer',
                'exists:provinces,id',
            ],

            'district_id' => [
                'nullable',
                'integer',
                'exists:districts,id',
            ],

            'sector_id' => [
                'nullable',
                'integer',
                'exists:sectors,id',
            ],

            'cell_id' => [
                'nullable',
                'integer',
                'exists:cells,id',
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

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = Farmer::query()
            ->with(
                $this->relations
            )

            ->when(
                $request->filled('search'),
                function (
                    $query
                ) use ($request) {
                    $search = trim(
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
                $request->filled('status'),
                fn ($query) =>
                    $query->where(
                        'status',
                        $request->status
                    )
            )

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

            ->orderBy('full_name')
            ->orderBy('id');

        $farmers = $query->paginate(
            min(
                max(
                    (int)
                    $request->get(
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
                    FarmerResource::collection(
                        $farmers
                            ->getCollection()
                    )->resolve(
                        $request
                    ),

                'pagination' => [
                    'current_page' =>
                        $farmers
                            ->currentPage(),

                    'last_page' =>
                        $farmers
                            ->lastPage(),

                    'per_page' =>
                        $farmers
                            ->perPage(),

                    'total' =>
                        $farmers->total(),

                    'from' =>
                        $farmers
                            ->firstItem(),

                    'to' =>
                        $farmers
                            ->lastItem(),
                ],
            ],
            'Farmers retrieved successfully.'
        );
    }

    public function store(
        StoreFarmerRequest $request
    ): JsonResponse {
        $farmer = DB::transaction(
            function () use ($request) {
                $data =
                    $request->validated();

                $data['farmer_code'] =
                    $this
                        ->generateFarmerCode();

                $data['status'] =
                    Farmer::STATUS_ACTIVE;

                $data['preferred_payment_method'] =
                    $data[
                        'preferred_payment_method'
                    ] ??
                    Farmer::PAYMENT_CASH;

                $data['created_by'] =
                    $request
                        ->user()
                        ->id;

                return Farmer::create(
                    $data
                );
            }
        );

        $farmer->load(
            $this->relations
        );

        return $this->sendResponse(
            new FarmerResource(
                $farmer
            ),
            'Farmer created successfully.',
            201
        );
    }

    public function show(
        Farmer $farmer
    ): JsonResponse {
        $farmer->load(
            $this->relations
        );

        return $this->sendResponse(
            new FarmerResource(
                $farmer
            ),
            'Farmer retrieved successfully.'
        );
    }

    public function update(
        UpdateFarmerRequest $request,
        Farmer $farmer
    ): JsonResponse {
        $data =
            $request->validated();

        $data['updated_by'] =
            $request
                ->user()
                ->id;

        $farmer->update($data);

        $farmer = $farmer
            ->fresh()
            ->load(
                $this->relations
            );

        return $this->sendResponse(
            new FarmerResource(
                $farmer
            ),
            'Farmer updated successfully.'
        );
    }

    public function deactivate(
        Request $request,
        Farmer $farmer
    ): JsonResponse {
        if (
            $farmer->status ===
            Farmer::STATUS_INACTIVE
        ) {
            return $this->sendResponse(
                new FarmerResource(
                    $farmer->load(
                        $this->relations
                    )
                ),
                'Farmer is already inactive.'
            );
        }

        $farmer->update([
            'status' =>
                Farmer::STATUS_INACTIVE,

            'deactivated_by' =>
                $request
                    ->user()
                    ->id,

            'deactivated_at' =>
                now(),

            'reactivated_by' =>
                null,

            'reactivated_at' =>
                null,

            'updated_by' =>
                $request
                    ->user()
                    ->id,
        ]);

        return $this->sendResponse(
            new FarmerResource(
                $farmer
                    ->fresh()
                    ->load(
                        $this->relations
                    )
            ),
            'Farmer deactivated successfully.'
        );
    }

    public function reactivate(
        Request $request,
        Farmer $farmer
    ): JsonResponse {
        if (
            $farmer->status ===
            Farmer::STATUS_ACTIVE
        ) {
            return $this->sendResponse(
                new FarmerResource(
                    $farmer->load(
                        $this->relations
                    )
                ),
                'Farmer is already active.'
            );
        }

        $farmer->update([
            'status' =>
                Farmer::STATUS_ACTIVE,

            'reactivated_by' =>
                $request
                    ->user()
                    ->id,

            'reactivated_at' =>
                now(),

            'deactivated_by' =>
                null,

            'deactivated_at' =>
                null,

            'updated_by' =>
                $request
                    ->user()
                    ->id,
        ]);

        return $this->sendResponse(
            new FarmerResource(
                $farmer
                    ->fresh()
                    ->load(
                        $this->relations
                    )
            ),
            'Farmer reactivated successfully.'
        );
    }

    private function generateFarmerCode(): string
    {
        $prefix = 'FRM-';

        $lastCode =
            Farmer::query()
                ->where(
                    'farmer_code',
                    'like',
                    $prefix . '%'
                )
                ->orderByDesc(
                    'farmer_code'
                )
                ->value(
                    'farmer_code'
                );

        $nextNumber = 1;

        if ($lastCode) {
            $nextNumber =
                ((int) substr(
                    $lastCode,
                    strlen($prefix)
                )) + 1;
        }

        do {
            $code =
                $prefix .
                str_pad(
                    (string)
                    $nextNumber,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            $exists =
                Farmer::query()
                    ->where(
                        'farmer_code',
                        $code
                    )
                    ->exists();

            $nextNumber++;
        } while ($exists);

        return $code;
    }
}
