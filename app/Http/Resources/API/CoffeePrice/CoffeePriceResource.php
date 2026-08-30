<?php

namespace App\Http\Resources\API\CoffeePrice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoffeePriceResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'coffee_season_id' =>
                $this->coffee_season_id,

            'code' =>
                $this->code,

            'coffee_type' =>
                $this->coffee_type,

            'coffee_type_label' =>
                match (
                    $this->coffee_type
                ) {
                    'cherry' =>
                        'Coffee Cherry',

                    'parchment' =>
                        'Parchment Coffee',

                    'green_coffee' =>
                        'Green Coffee',

                    default =>
                        ucfirst(
                            str_replace(
                                '_',
                                ' ',
                                $this->coffee_type
                            )
                        ),
                },

            'price_per_kg' =>
                (float)
                $this->price_per_kg,

            'currency' =>
                $this->currency,

            'effective_from' =>
                $this->effective_from
                    ?->format(
                        'Y-m-d'
                    ),

            'effective_to' =>
                $this->effective_to
                    ?->format(
                        'Y-m-d'
                    ),

            'status' =>
                $this->status,

            'is_active' =>
                $this->status ===
                'active',

            'notes' =>
                $this->notes,

            'season' =>
                $this->whenLoaded(
                    'season',
                    fn () => [
                        'id' =>
                            $this->season->id,

                        'name' =>
                            $this->season->name,

                        'code' =>
                            $this->season->code,

                        'status' =>
                            $this->season->status,

                        'start_date' =>
                            $this
                                ->season
                                ->start_date
                                ?->format(
                                    'Y-m-d'
                                ),

                        'end_date' =>
                            $this
                                ->season
                                ->end_date
                                ?->format(
                                    'Y-m-d'
                                ),
                    ]
                ),

            'creator' =>
                $this->whenLoaded(
                    'creator',
                    fn () => [
                        'id' =>
                            $this->creator->id,

                        'name' =>
                            $this->creator->name,
                    ]
                ),

            'activator' =>
                $this->whenLoaded(
                    'activator',
                    fn () =>
                        $this->activator
                            ? [
                                'id' =>
                                    $this
                                        ->activator
                                        ->id,

                                'name' =>
                                    $this
                                        ->activator
                                        ->name,
                            ]
                            : null
                ),

            'deactivator' =>
                $this->whenLoaded(
                    'deactivator',
                    fn () =>
                        $this->deactivator
                            ? [
                                'id' =>
                                    $this
                                        ->deactivator
                                        ->id,

                                'name' =>
                                    $this
                                        ->deactivator
                                        ->name,
                            ]
                            : null
                ),

            'activated_at' =>
                $this->activated_at
                    ?->toISOString(),

            'deactivated_at' =>
                $this->deactivated_at
                    ?->toISOString(),

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}
