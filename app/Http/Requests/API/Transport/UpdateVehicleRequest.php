<?php

namespace App\Http\Requests\API\Transport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $vehicle = $this->route('vehicle');

        return [
            'registration_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique(
                    'vehicles',
                    'registration_number'
                )->ignore($vehicle?->id),
            ],

            'vehicle_type' => [
                'sometimes',
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
