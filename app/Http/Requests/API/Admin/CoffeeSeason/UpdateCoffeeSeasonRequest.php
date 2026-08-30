<?php

namespace App\Http\Requests\API\Admin\CoffeeSeason;

use App\Models\CoffeeSeason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCoffeeSeasonRequest extends FormRequest
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
        $season = $this->route(
            'coffeeSeason'
        );

        $seasonId =
            $season instanceof CoffeeSeason
                ? $season->id
                : $season;

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique(
                    'coffee_seasons',
                    'name'
                )->ignore($seasonId),
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
}
