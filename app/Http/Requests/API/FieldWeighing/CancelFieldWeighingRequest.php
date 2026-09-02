<?php

namespace App\Http\Requests\API\FieldWeighing;

use Illuminate\Foundation\Http\FormRequest;

class CancelFieldWeighingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'balance';
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => [
                'required',
                'string',
                'min:3',
                'max:2000',
            ],
        ];
    }
}
