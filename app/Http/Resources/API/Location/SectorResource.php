<?php

namespace App\Http\Resources\API\Location;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'district_id' => $this->district_id,

            'name' => $this->name,

            'is_active' => (bool) $this->is_active,

            'district' => $this->whenLoaded(
                'district',
                fn () => [
                    'id' => $this->district->id,
                    'name' => $this->district->name,

                    'province_id' =>
                        $this->district->province_id,
                ]
            ),

            'cells_count' => $this->whenCounted(
                'cells'
            ),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
