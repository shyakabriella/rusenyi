<?php

namespace App\Http\Requests\API\Agent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'admin',
            'accountant',
        ]) ?? false;
    }

    public function rules(): array
    {
        return [
            'assigned_location_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:locations,id',
            ],

            'working_since' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
