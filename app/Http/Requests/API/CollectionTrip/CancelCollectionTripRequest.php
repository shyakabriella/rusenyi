<?php

namespace App\Http\Requests\API\CollectionTrip;

use Illuminate\Foundation\Http\FormRequest;

class CancelCollectionTripRequest extends FormRequest
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
            'cancellation_reason' => [
                'required',
                'string',
                'min:3',
                'max:2000',
            ],
        ];
    }
}
