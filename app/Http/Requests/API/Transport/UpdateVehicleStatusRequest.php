<?php

namespace App\Http\Requests\API\Transport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    'available',
                    'maintenance',
                    'inactive',
                ]),
            ],
        ];
    }
}
