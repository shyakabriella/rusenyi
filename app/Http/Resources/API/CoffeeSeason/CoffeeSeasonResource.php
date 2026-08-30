<?php

namespace App\Http\Resources\API\CoffeeSeason;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoffeeSeasonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,

            'code' => $this->code,

            'start_date' =>
                $this->start_date?->format(
                    'Y-m-d'
                ),

            'end_date' =>
                $this->end_date?->format(
                    'Y-m-d'
                ),

            'status' => $this->status,

            'is_active' =>
                $this->status === 'active',

            'description' =>
                $this->description,

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

            'closer' =>
                $this->whenLoaded(
                    'closer',
                    fn () =>
                        $this->closer
                            ? [
                                'id' =>
                                    $this
                                        ->closer
                                        ->id,

                                'name' =>
                                    $this
                                        ->closer
                                        ->name,
                            ]
                            : null
                ),

            'activated_at' =>
                $this->activated_at
                    ?->toISOString(),

            'closed_at' =>
                $this->closed_at
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
