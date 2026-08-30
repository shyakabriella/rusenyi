<?php

namespace App\Http\Requests\API\Admin\Location;

use App\Models\Province;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProvinceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }
    }

    public function rules(): array
    {
        $province = $this->route('province');

        $provinceId = $province instanceof Province
            ? $province->id
            : $province;

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('provinces', 'name')
                    ->ignore($provinceId),
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' =>
                'Province name is required.',

            'name.unique' =>
                'A province with this name already exists.',

            'name.max' =>
                'Province name may not exceed 150 characters.',

            'is_active.boolean' =>
                'Province status must be true or false.',
        ];
    }
}
