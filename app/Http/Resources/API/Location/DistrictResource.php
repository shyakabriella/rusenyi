<?php

namespace App\Http\Resources\API\Location;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistrictResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'province_id' => $this->province_id,

            'name' => $this->name,

            'is_active' => (bool) $this->is_active,

            'province' => $this->whenLoaded(
                'province',
                fn () => [
                    'id' => $this->province->id,
                    'name' => $this->province->name,
                ]
            ),

            'sectors_count' => $this->whenCounted(
                'sectors'
            ),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
