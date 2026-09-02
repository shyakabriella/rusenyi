<?php

namespace App\Http\Requests\API\ProcessingYield;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProcessingYieldRequest extends FormRequest
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
            'output_quantity_kg' => [
                'sometimes',
                'required',
                'numeric',
                'gt:0',
            ],

            'output_coffee_type' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:50',
            ],

            'output_bag_count' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'yield_date' => [
                'nullable',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }
}
