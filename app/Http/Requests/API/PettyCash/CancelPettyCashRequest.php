<?php

namespace App\Http\Requests\API\PettyCash;

use Illuminate\Foundation\Http\FormRequest;

class CancelPettyCashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role ===
            'accountant';
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:3000',
            ],
        ];
    }
}
