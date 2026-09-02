<?php

namespace App\Http\Requests\API\CollectionTrip;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->role,
            ['admin', 'balance'],
            true
        );
    }

    public function rules(): array
    {
        return [
            'driver_user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'vehicle_registration' => [
                'nullable',
                'string',
                'max:50',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
