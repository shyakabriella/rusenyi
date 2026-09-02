<?php

namespace App\Http\Requests\API\CoffeeLot;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoffeeLotRequest extends FormRequest
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
            'source_type' => [
                'required',
                Rule::in([
                    'factory_reception',
                    'direct_farmer_delivery',
                ]),
            ],

            'source_id' => [
                'required',
                'integer',
                'min:1',
            ],

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

            'lot_date' => [
                'required',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
