<?php

namespace App\Http\Resources\API\Location;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CellResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'sector_id' => $this->sector_id,

            'name' => $this->name,

            'is_active' => (bool) $this->is_active,

            'sector' => $this->whenLoaded(
                'sector',
                fn () => [
                    'id' => $this->sector->id,
                    'name' => $this->sector->name,
                    'district_id' => $this->sector->district_id,
                ]
            ),

            'villages_count' => $this->whenCounted(
                'villages'
            ),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
