<?php

namespace App\Http\Resources\API\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CashAllocationResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $proofUrl = null;

        if ($this->payment_proof_path) {
            $storageUrl =
                Storage::disk('public')
                    ->url(
                        $this->payment_proof_path
                    );

            $proofUrl =
                str_starts_with(
                    $storageUrl,
                    'http://'
                ) ||
                str_starts_with(
                    $storageUrl,
                    'https://'
                )
                    ? $storageUrl
                    : url($storageUrl);
        }

        return [
            'id' =>
                $this->id,

            'allocation_code' =>
                $this->allocation_code,

            'coffee_season_id' =>
                $this->coffee_season_id,

            'agent_id' =>
                $this->agent_id,

            'amount' =>
                $this->amount,

            'currency' =>
                $this->currency,

            'payment_method' =>
                $this->payment_method,

            /*
             * Keep reference for compatibility.
             */
            'reference' =>
                $this->reference,

            /*
             * Clearer alias for new frontend/mobile code.
             */
            'payment_reference' =>
                $this->reference,

            'payment_proof' => [
                'exists' =>
                    (bool)
                    $this->payment_proof_path,

                'url' =>
                    $proofUrl,

                'original_name' =>
                    $this
                        ->payment_proof_original_name,

                'mime_type' =>
                    $this
                        ->payment_proof_mime_type,

                'size' =>
                    $this
                        ->payment_proof_size,

                'uploaded_at' =>
                    $this
                        ->payment_proof_uploaded_at
                        ?->toISOString(),
            ],

            'allocation_date' =>
                $this->allocation_date
                    ?->format('Y-m-d'),

            'purpose' =>
                $this->purpose,

            'notes' =>
                $this->notes,

            'status' =>
                $this->status,

            'is_draft' =>
                $this->status ===
                'draft',

            'is_approved' =>
                $this->status ===
                'approved',

            'coffee_season' =>
                $this->whenLoaded(
                    'coffeeSeason',
                    fn () => [
                        'id' =>
                            $this
                                ->coffeeSeason
                                ->id,

                        'code' =>
                            $this
                                ->coffeeSeason
                                ->code,

                        'name' =>
                            $this
                                ->coffeeSeason
                                ->name,

                        'status' =>
                            $this
                                ->coffeeSeason
                                ->status,
                    ]
                ),

            'agent' =>
                $this->whenLoaded(
                    'agent',
                    fn () => [
                        'id' =>
                            $this->agent->id,

                        'agent_code' =>
                            $this
                                ->agent
                                ->agent_code,

                        'status' =>
                            $this
                                ->agent
                                ->status,

                        'user' =>
                            $this
                                ->agent
                                ->user
                                ? [
                                    'id' =>
                                        $this
                                            ->agent
                                            ->user
                                            ->id,

                                    'name' =>
                                        $this
                                            ->agent
                                            ->user
                                            ->name,

                                    'phone' =>
                                        $this
                                            ->agent
                                            ->user
                                            ->phone,

                                    'email' =>
                                        $this
                                            ->agent
                                            ->user
                                            ->email,
                                ]
                                : null,
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

            'updater' =>
                $this->whenLoaded(
                    'updater',
                    fn () =>
                        $this->updater
                            ? [
                                'id' =>
                                    $this
                                        ->updater
                                        ->id,

                                'name' =>
                                    $this
                                        ->updater
                                        ->name,
                            ]
                            : null
                ),

            'approver' =>
                $this->whenLoaded(
                    'approver',
                    fn () =>
                        $this->approver
                            ? [
                                'id' =>
                                    $this
                                        ->approver
                                        ->id,

                                'name' =>
                                    $this
                                        ->approver
                                        ->name,
                            ]
                            : null
                ),

            'canceller' =>
                $this->whenLoaded(
                    'canceller',
                    fn () =>
                        $this->canceller
                            ? [
                                'id' =>
                                    $this
                                        ->canceller
                                        ->id,

                                'name' =>
                                    $this
                                        ->canceller
                                        ->name,
                            ]
                            : null
                ),

            'payment_proof_uploader' =>
                $this->whenLoaded(
                    'paymentProofUploader',
                    fn () =>
                        $this
                            ->paymentProofUploader
                            ? [
                                'id' =>
                                    $this
                                        ->paymentProofUploader
                                        ->id,

                                'name' =>
                                    $this
                                        ->paymentProofUploader
                                        ->name,
                            ]
                            : null
                ),

            'approved_at' =>
                $this->approved_at
                    ?->toISOString(),

            'cancelled_at' =>
                $this->cancelled_at
                    ?->toISOString(),

            'cancellation_reason' =>
                $this
                    ->cancellation_reason,

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}
