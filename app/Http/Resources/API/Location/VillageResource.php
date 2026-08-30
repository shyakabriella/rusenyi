<?php

namespace App\Http\Resources\API\Location;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VillageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'cell_id' => $this->cell_id,

            'name' => $this->name,

            'is_active' => (bool) $this->is_active,

            'cell' => $this->whenLoaded(
                'cell',
                fn () => [
                    'id' => $this->cell->id,
                    'name' => $this->cell->name,
                    'sector_id' => $this->cell->sector_id,
                ]
            ),

            'collection_points_count' => $this->whenCounted(
                'collectionPoints'
            ),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
