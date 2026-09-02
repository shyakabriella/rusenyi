<?php

namespace App\Http\Requests\API\FieldWeighing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFieldWeighingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'balance';
    }

    public function rules(): array
    {
        return [
            'field_weight_kg' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999.99',
            ],

            'bag_count' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'weighed_at' => [
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
