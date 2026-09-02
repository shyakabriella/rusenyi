<?php

namespace App\Http\Resources\API\Agent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $village =
            $this->relationLoaded('homeVillage')
                ? $this->homeVillage
                : null;

        $cell =
            $village &&
            $village->relationLoaded('cell')
                ? $village->cell
                : null;

        $sector =
            $cell &&
            $cell->relationLoaded('sector')
                ? $cell->sector
                : null;

        $district =
            $sector &&
            $sector->relationLoaded('district')
                ? $sector->district
                : null;

        $province =
            $district &&
            $district->relationLoaded('province')
                ? $district->province
                : null;

        return [
            'id' =>
                $this->id,

            'agent_code' =>
                $this->agent_code,

            'status' =>
                $this->status,

            'is_active' =>
                $this->status === 'active',

            'user_id' =>
                $this->user_id,

            'user' =>
                $this->relationLoaded('user') &&
                $this->user
                    ? [
                        'id' =>
                            $this->user->id,

                        'name' =>
                            $this->user->name,

                        'email' =>
                            $this->user->email,

                        'phone' =>
                            $this->user->phone,

                        'status' =>
                            $this->user->status
                                ?? null,
                    ]
                    : null,

            'home_village_id' =>
                $this->home_village_id,

            /*
            |--------------------------------------------------------------------------
            | Complete Rwanda Home Location
            |--------------------------------------------------------------------------
            */

            'home_location' => [
                'province' =>
                    $province
                        ? [
                            'id' =>
                                $province->id,

                            'name' =>
                                $province->name,
                        ]
                        : null,

                'district' =>
                    $district
                        ? [
                            'id' =>
                                $district->id,

                            'name' =>
                                $district->name,
                        ]
                        : null,

                'sector' =>
                    $sector
                        ? [
                            'id' =>
                                $sector->id,

                            'name' =>
                                $sector->name,
                        ]
                        : null,

                'cell' =>
                    $cell
                        ? [
                            'id' =>
                                $cell->id,

                            'name' =>
                                $cell->name,
                        ]
                        : null,

                'village' =>
                    $village
                        ? [
                            'id' =>
                                $village->id,

                            'name' =>
                                $village->name,
                        ]
                        : null,
            ],

            'notes' =>
                $this->notes,

            /*
            |--------------------------------------------------------------------------
            | Audit Information
            |--------------------------------------------------------------------------
            */

            'created_by_id' =>
                $this->created_by,

            'created_by' =>
                $this->relationLoaded('creator') &&
                $this->creator
                    ? [
                        'id' =>
                            $this->creator->id,

                        'name' =>
                            $this->creator->name,
                    ]
                    : null,

            'creator' =>
                $this->relationLoaded('creator') &&
                $this->creator
                    ? [
                        'id' =>
                            $this->creator->id,

                        'name' =>
                            $this->creator->name,
                    ]
                    : null,

            'updated_by_id' =>
                $this->updated_by,

            'updated_by' =>
                $this->relationLoaded('updater') &&
                $this->updater
                    ? [
                        'id' =>
                            $this->updater->id,

                        'name' =>
                            $this->updater->name,
                    ]
                    : null,

            'updater' =>
                $this->relationLoaded('updater') &&
                $this->updater
                    ? [
                        'id' =>
                            $this->updater->id,

                        'name' =>
                            $this->updater->name,
                    ]
                    : null,

            /*
            |--------------------------------------------------------------------------
            | Deactivation Information
            |--------------------------------------------------------------------------
            */

            'deactivated_by_id' =>
                $this->deactivated_by,

            'deactivated_by' =>
                $this->relationLoaded('deactivator') &&
                $this->deactivator
                    ? [
                        'id' =>
                            $this->deactivator->id,

                        'name' =>
                            $this->deactivator->name,
                    ]
                    : null,

            'deactivator' =>
                $this->relationLoaded('deactivator') &&
                $this->deactivator
                    ? [
                        'id' =>
                            $this->deactivator->id,

                        'name' =>
                            $this->deactivator->name,
                    ]
                    : null,

            'deactivated_at' =>
                $this->deactivated_at
                    ?->toISOString(),

            /*
            |--------------------------------------------------------------------------
            | Reactivation Information
            |--------------------------------------------------------------------------
            */

            'reactivated_by_id' =>
                $this->reactivated_by,

            'reactivated_by' =>
                $this->relationLoaded('reactivator') &&
                $this->reactivator
                    ? [
                        'id' =>
                            $this->reactivator->id,

                        'name' =>
                            $this->reactivator->name,
                    ]
                    : null,

            'reactivator' =>
                $this->relationLoaded('reactivator') &&
                $this->reactivator
                    ? [
                        'id' =>
                            $this->reactivator->id,

                        'name' =>
                            $this->reactivator->name,
                    ]
                    : null,

            'reactivated_at' =>
                $this->reactivated_at
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
