<?php

namespace App\Http\Requests\API\PettyCash;

use Illuminate\Foundation\Http\FormRequest;

class StorePettyCashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role ===
            'accountant';
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'purpose' => [
                'required',
                'string',
                'min:3',
                'max:3000',
            ],
        ];
    }
}
