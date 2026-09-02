<?php

namespace App\Http\Requests\API\WeightReconciliation;

use Illuminate\Foundation\Http\FormRequest;

class StoreWeightReconciliationRequest extends FormRequest
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
            'factory_reception_id' => [
                'required',
                'integer',
                'exists:factory_receptions,id',
            ],

            'tolerance_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }
}
