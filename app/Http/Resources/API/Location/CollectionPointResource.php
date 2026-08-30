<?php

namespace App\Http\Resources\API\Location;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionPointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $village = $this->relationLoaded('village')
            ? $this->village
            : null;

        $cell = $village &&
            $village->relationLoaded('cell')
                ? $village->cell
                : null;

        $sector = $cell &&
            $cell->relationLoaded('sector')
                ? $cell->sector
                : null;

        $district = $sector &&
            $sector->relationLoaded('district')
                ? $sector->district
                : null;

        $province = $district &&
            $district->relationLoaded('province')
                ? $district->province
                : null;

        return [
            'id' => $this->id,

            'village_id' =>
                $this->village_id,

            'name' => $this->name,

            'code' => $this->code,

            'latitude' =>
                $this->latitude !== null
                    ? (float) $this->latitude
                    : null,

            'longitude' =>
                $this->longitude !== null
                    ? (float) $this->longitude
                    : null,

            'description' =>
                $this->description,

            'is_active' =>
                (bool) $this->is_active,

            'location' => $village
                ? [
                    'province' => $province
                        ? [
                            'id' =>
                                $province->id,
                            'name' =>
                                $province->name,
                        ]
                        : null,

                    'district' => $district
                        ? [
                            'id' =>
                                $district->id,
                            'name' =>
                                $district->name,
                        ]
                        : null,

                    'sector' => $sector
                        ? [
                            'id' =>
                                $sector->id,
                            'name' =>
                                $sector->name,
                        ]
                        : null,

                    'cell' => $cell
                        ? [
                            'id' =>
                                $cell->id,
                            'name' =>
                                $cell->name,
                        ]
                        : null,

                    'village' => [
                        'id' =>
                            $village->id,
                        'name' =>
                            $village->name,
                    ],
                ]
                : null,

            'agents_count' =>
                $this->whenCounted(
                    'agents'
                ),

            'agents' =>
                $this->whenLoaded(
                    'agents',
                    fn () =>
                        $this->agents
                            ->map(
                                fn ($agent) => [
                                    'id' =>
                                        $agent->id,
                                    'name' =>
                                        $agent->name,
                                    'email' =>
                                        $agent->email,
                                    'phone' =>
                                        $agent->phone,
                                    'role' =>
                                        $agent->role,
                                    'assignment_active' =>
                                        (bool) $agent
                                            ->pivot
                                            ->is_active,
                                    'assigned_at' =>
                                        $agent
                                            ->pivot
                                            ->assigned_at,
                                    'unassigned_at' =>
                                        $agent
                                            ->pivot
                                            ->unassigned_at,
                                ]
                            )
                            ->values()
                ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
