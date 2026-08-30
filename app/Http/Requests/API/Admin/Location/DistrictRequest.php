<?php

namespace App\Http\Requests\API\Admin\Location;

use App\Models\District;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DistrictRequest extends FormRequest
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
        $district = $this->route('district');

        $districtId = $district instanceof District
            ? $district->id
            : $district;

        return [
            'province_id' => [
                'required',
                'integer',
                'exists:provinces,id',
            ],

            'name' => [
                'required',
                'string',
                'max:150',

                Rule::unique('districts', 'name')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'province_id',
                                $this->integer('province_id')
                            )
                    )
                    ->ignore($districtId),
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
            'province_id.required' =>
                'Province is required.',

            'province_id.exists' =>
                'The selected province does not exist.',

            'name.required' =>
                'District name is required.',

            'name.unique' =>
                'This district already exists in the selected province.',

            'name.max' =>
                'District name may not exceed 150 characters.',

            'is_active.boolean' =>
                'District status must be true or false.',
        ];
    }
}
