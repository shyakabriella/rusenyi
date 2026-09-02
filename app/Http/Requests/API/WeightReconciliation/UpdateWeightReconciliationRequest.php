<?php

namespace App\Http\Requests\API\WeightReconciliation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWeightReconciliationRequest extends FormRequest
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
            'tolerance_percentage' => [
                'sometimes',
                'required',
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
