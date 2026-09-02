<?php

namespace App\Http\Requests\API\Admin\Agent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) {
            $notes = trim(
                (string) $this->input(
                    'notes'
                )
            );

            $this->merge([
                'notes' =>
                    $notes === ''
                        ? null
                        : $notes,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'home_village_id' => [
                'nullable',
                'integer',
                'exists:villages,id',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
