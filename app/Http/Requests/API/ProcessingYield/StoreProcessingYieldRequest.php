<?php

namespace App\Http\Requests\API\ProcessingYield;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcessingYieldRequest extends FormRequest
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
            'processing_batch_id' => [
                'required',
                'integer',
                'exists:processing_batches,id',
            ],

            'output_quantity_kg' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'output_coffee_type' => [
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
