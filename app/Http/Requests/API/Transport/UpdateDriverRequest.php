<?php

namespace App\Http\Requests\API\Transport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $driver = $this->route('driver');

        return [
            'license_number' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique(
                    'drivers',
                    'license_number'
                )->ignore($driver?->id),
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
