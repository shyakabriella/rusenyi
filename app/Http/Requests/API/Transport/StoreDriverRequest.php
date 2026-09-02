<?php

namespace App\Http\Requests\API\Transport;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                'unique:drivers,user_id',
            ],

            'license_number' => [
                'nullable',
                'string',
                'max:80',
                'unique:drivers,license_number',
            ],

            'license_category' => [
                'nullable',
                'string',
                'max:50',
            ],

            'license_expiry_date' => [
                'nullable',
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
