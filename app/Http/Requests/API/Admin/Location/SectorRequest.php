<?php

namespace App\Http\Requests\API\Admin\Location;

use App\Models\Sector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectorRequest extends FormRequest
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
        $sector = $this->route('sector');

        $sectorId = $sector instanceof Sector
            ? $sector->id
            : $sector;

        return [
            'district_id' => [
                'required',
                'integer',
                'exists:districts,id',
            ],

            'name' => [
                'required',
                'string',
                'max:150',

                Rule::unique('sectors', 'name')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'district_id',
                                $this->integer('district_id')
                            )
                    )
                    ->ignore($sectorId),
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
            'district_id.required' =>
                'District is required.',

            'district_id.exists' =>
                'The selected district does not exist.',

            'name.required' =>
                'Sector name is required.',

            'name.unique' =>
                'This sector already exists in the selected district.',

            'name.max' =>
                'Sector name may not exceed 150 characters.',

            'is_active.boolean' =>
                'Sector status must be true or false.',
        ];
    }
}
