<?php

namespace App\Http\Requests\API\FactoryReception;

use Illuminate\Foundation\Http\FormRequest;

class StoreFactoryReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'balance';
    }

    public function rules(): array
    {
        return [
            'field_weighing_id' => [
                'required',
                'integer',
                'exists:field_weighings,id',
            ],

            'factory_weight_kg' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'bag_count' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'received_at' => [
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
