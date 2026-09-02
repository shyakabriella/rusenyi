<?php

namespace App\Http\Requests\API\CoffeeLot;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCoffeeLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->role,
            ['admin', 'store'],
            true
        );
    }

    public function rules(): array
    {
        return [
            'bag_count' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'storage_location' => [
                'nullable',
                'string',
                'max:150',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
