<?php

namespace App\Http\Requests\API\Admin\CoffeeSeason;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoffeeSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('name')) {
            $data['name'] = trim(
                (string) $this->input('name')
            );
        }

        if ($this->has('description')) {
            $description = trim(
                (string) $this->input('description')
            );

            $data['description'] =
                $description === ''
                    ? null
                    : $description;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique(
                    'coffee_seasons',
                    'name'
                ),
            ],

            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' =>
                'Coffee season name is required.',

            'name.unique' =>
                'A coffee season with this name already exists.',

            'start_date.required' =>
                'Start date is required.',

            'end_date.after_or_equal' =>
                'End date cannot be before the start date.',
        ];
    }
}
