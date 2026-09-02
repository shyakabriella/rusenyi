<?php

namespace App\Http\Requests\API\Processing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProcessingBatchRequest extends FormRequest
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
            'process_name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:100',
            ],

            'input_quantity_kg' => [
                'sometimes',
                'required',
                'numeric',
                'gt:0',
            ],

            'planned_start_at' => [
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
