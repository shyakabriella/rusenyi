<?php

namespace App\Http\Controllers\API\CoffeeSeason;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\API\CoffeeSeason\CoffeeSeasonResource;
use App\Models\CoffeeSeason;
use Illuminate\Http\JsonResponse;

class ActiveCoffeeSeasonController extends BaseController
{
    public function __invoke(): JsonResponse
    {
        $season =
            CoffeeSeason::query()
                ->with([
                    'creator',
                    'activator',
                ])
                ->where(
                    'status',
                    CoffeeSeason::STATUS_ACTIVE
                )
                ->first();

        if (!$season) {
            return $this->sendResponse(
                null,
                'No active coffee season found.'
            );
        }

        return $this->sendResponse(
            new CoffeeSeasonResource(
                $season
            ),
            'Active coffee season retrieved successfully.'
        );
    }
}
