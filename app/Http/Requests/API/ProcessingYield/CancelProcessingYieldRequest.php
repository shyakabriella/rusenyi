<?php

namespace App\Http\Requests\API\ProcessingYield;

use Illuminate\Foundation\Http\FormRequest;

class CancelProcessingYieldRequest extends FormRequest
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
            'cancellation_reason' => [
                'required',
                'string',
                'min:3',
                'max:3000',
            ],
        ];
    }
}
