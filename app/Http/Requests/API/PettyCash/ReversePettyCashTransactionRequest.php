<?php

namespace App\Http\Requests\API\PettyCash;

use Illuminate\Foundation\Http\FormRequest;

class ReversePettyCashTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->role,
            ['admin', 'accountant'],
            true
        );
    }

    public function rules(): array
    {
        return [
            'reversal_reason' => [
                'required',
                'string',
                'min:3',
                'max:3000',
            ],
        ];
    }
}
