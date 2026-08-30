<?php

namespace App\Http\Controllers\API\CoffeePrice;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\API\CoffeePrice\CoffeePriceResource;
use App\Http\Resources\API\CoffeeSeason\CoffeeSeasonResource;
use App\Models\CoffeePrice;
use App\Models\CoffeeSeason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActiveCoffeePriceController extends BaseController
{
    public function __invoke(
        Request $request
    ): JsonResponse {
        $request->validate([
            'coffee_type' => [
                'nullable',
                'in:cherry,parchment,green_coffee',
            ],
        ]);

        $season =
            CoffeeSeason::query()
                ->where(
                    'status',
                    CoffeeSeason::STATUS_ACTIVE
                )
                ->first();

        if (!$season) {
            return $this->sendResponse(
                [
                    'season' => null,
                    'prices' => [],
                ],
                'No active coffee season found.'
            );
        }

        $prices =
            CoffeePrice::query()
                ->with([
                    'season',
                    'creator',
                    'activator',
                ])
                ->where(
                    'coffee_season_id',
                    $season->id
                )
                ->where(
                    'status',
                    CoffeePrice::STATUS_ACTIVE
                )
                ->when(
                    $request->filled(
                        'coffee_type'
                    ),
                    fn ($query) =>
                        $query->where(
                            'coffee_type',
                            $request
                                ->coffee_type
                        )
                )
                ->orderBy(
                    'coffee_type'
                )
                ->get();

        return $this->sendResponse(
            [
                'season' =>
                    (
                        new CoffeeSeasonResource(
                            $season
                        )
                    )->resolve(
                        $request
                    ),

                'prices' =>
                    CoffeePriceResource::collection(
                        $prices
                    )->resolve(
                        $request
                    ),
            ],
            'Active coffee prices retrieved successfully.'
        );
    }
}
