<?php

namespace App\Http\Requests\API\WeightReconciliation;

use Illuminate\Foundation\Http\FormRequest;

class CancelWeightReconciliationRequest extends FormRequest
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
            'cancellation_reason' => [
                'required',
                'string',
                'min:3',
                'max:3000',
            ],
        ];
    }
}
