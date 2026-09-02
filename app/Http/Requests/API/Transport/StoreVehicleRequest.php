<?php

namespace App\Http\Requests\API\Transport;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'registration_number' => [
                'required',
                'string',
                'max:50',
                'unique:vehicles,registration_number',
            ],

            'vehicle_type' => [
                'required',
                'string',
                'max:50',
            ],

            'make' => [
                'nullable',
                'string',
                'max:80',
            ],

            'model' => [
                'nullable',
                'string',
                'max:80',
            ],

            'manufacture_year' => [
                'nullable',
                'integer',
                'min:1950',
                'max:' . now()->year,
            ],

            'capacity_kg' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:99999999',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
