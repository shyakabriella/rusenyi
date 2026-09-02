<?php

namespace App\Http\Resources\API\Farmer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FarmerResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'farmer_code' =>
                $this->farmer_code,

            'full_name' =>
                $this->full_name,

            'phone' =>
                $this->phone,

            'national_id' =>
                $this->national_id,

            'gender' =>
                $this->gender,

            'preferred_payment_method' =>
                $this->preferred_payment_method,

            'address_note' =>
                $this->address_note,

            'notes' =>
                $this->notes,

            'status' =>
                $this->status,

            'is_active' =>
                $this->status ===
                'active',

            'village_id' =>
                $this->village_id,

            'collection_point_id' =>
                $this->collection_point_id,

            'location' =>
                $this->whenLoaded(
                    'village',
                    fn () => [
                        'province' => [
                            'id' =>
                                $this->village
                                    ->cell
                                    ->sector
                                    ->district
                                    ->province
                                    ->id,

                            'name' =>
                                $this->village
                                    ->cell
                                    ->sector
                                    ->district
                                    ->province
                                    ->name,
                        ],

                        'district' => [
                            'id' =>
                                $this->village
                                    ->cell
                                    ->sector
                                    ->district
                                    ->id,

                            'name' =>
                                $this->village
                                    ->cell
                                    ->sector
                                    ->district
                                    ->name,
                        ],

                        'sector' => [
                            'id' =>
                                $this->village
                                    ->cell
                                    ->sector
                                    ->id,

                            'name' =>
                                $this->village
                                    ->cell
                                    ->sector
                                    ->name,
                        ],

                        'cell' => [
                            'id' =>
                                $this->village
                                    ->cell
                                    ->id,

                            'name' =>
                                $this->village
                                    ->cell
                                    ->name,
                        ],

                        'village' => [
                            'id' =>
                                $this->village->id,

                            'name' =>
                                $this->village->name,
                        ],
                    ]
                ),

            'collection_point' =>
                $this->whenLoaded(
                    'collectionPoint',
                    fn () =>
                        $this->collectionPoint
                            ? [
                                'id' =>
                                    $this
                                        ->collectionPoint
                                        ->id,

                                'name' =>
                                    $this
                                        ->collectionPoint
                                        ->name,

                                'code' =>
                                    $this
                                        ->collectionPoint
                                        ->code,
                            ]
                            : null
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

            'updater' =>
                $this->whenLoaded(
                    'updater',
                    fn () =>
                        $this->updater
                            ? [
                                'id' =>
                                    $this->updater->id,

                                'name' =>
                                    $this->updater->name,
                            ]
                            : null
                ),

            'deactivated_by' =>
                $this->whenLoaded(
                    'deactivator',
                    fn () =>
                        $this->deactivator
                            ? [
                                'id' =>
                                    $this->deactivator->id,

                                'name' =>
                                    $this->deactivator->name,
                            ]
                            : null
                ),

            'reactivated_by' =>
                $this->whenLoaded(
                    'reactivator',
                    fn () =>
                        $this->reactivator
                            ? [
                                'id' =>
                                    $this->reactivator->id,

                                'name' =>
                                    $this->reactivator->name,
                            ]
                            : null
                ),

            'deactivated_at' =>
                $this->deactivated_at
                    ?->toISOString(),

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
