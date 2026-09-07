<?php

namespace App\Http\Requests\API\PettyCash;

use Illuminate\Foundation\Http\FormRequest;

class RejectPettyCashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role ===
            'admin';
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
