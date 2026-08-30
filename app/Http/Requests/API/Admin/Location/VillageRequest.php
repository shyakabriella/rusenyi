<?php

namespace App\Http\Requests\API\Admin\Location;

use App\Models\Village;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VillageRequest extends FormRequest
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
        $village = $this->route('village');

        $villageId = $village instanceof Village
            ? $village->id
            : $village;

        return [
            'cell_id' => [
                'required',
                'integer',
                'exists:cells,id',
            ],

            'name' => [
                'required',
                'string',
                'max:150',

                Rule::unique('villages', 'name')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'cell_id',
                                $this->integer('cell_id')
                            )
                    )
                    ->ignore($villageId),
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
            'cell_id.required' =>
                'Cell is required.',

            'cell_id.exists' =>
                'The selected cell does not exist.',

            'name.required' =>
                'Village name is required.',

            'name.unique' =>
                'This village already exists in the selected cell.',

            'name.max' =>
                'Village name may not exceed 150 characters.',

            'is_active.boolean' =>
                'Village status must be true or false.',
        ];
    }
}
