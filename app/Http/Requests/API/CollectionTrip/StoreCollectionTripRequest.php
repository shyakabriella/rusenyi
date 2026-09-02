<?php

namespace App\Http\Requests\API\CollectionTrip;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionTripRequest extends FormRequest
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
            'field_weighing_id' => [
                'required',
                'integer',
                'exists:field_weighings,id',
            ],

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
