<?php

namespace App\Http\Requests\API\Transport;

use Illuminate\Foundation\Http\FormRequest;

class AssignVehicleDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'driver_id' => [
                'required',
                'integer',
                'exists:drivers,id',
            ],
        ];
    }
}
