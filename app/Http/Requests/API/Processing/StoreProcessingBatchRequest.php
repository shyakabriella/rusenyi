<?php

namespace App\Http\Requests\API\Processing;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcessingBatchRequest extends FormRequest
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
            'store_inventory_id' => [
                'required',
                'integer',
                'exists:store_inventories,id',
            ],

            'process_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],

            'input_quantity_kg' => [
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
