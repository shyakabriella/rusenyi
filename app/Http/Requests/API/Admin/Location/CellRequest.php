<?php

namespace App\Http\Requests\API\Admin\Location;

use App\Models\Cell;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CellRequest extends FormRequest
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
        $cell = $this->route('cell');

        $cellId = $cell instanceof Cell
            ? $cell->id
            : $cell;

        return [
            'sector_id' => [
                'required',
                'integer',
                'exists:sectors,id',
            ],

            'name' => [
                'required',
                'string',
                'max:150',

                Rule::unique('cells', 'name')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'sector_id',
                                $this->integer('sector_id')
                            )
                    )
                    ->ignore($cellId),
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
            'sector_id.required' =>
                'Sector is required.',

            'sector_id.exists' =>
                'The selected sector does not exist.',

            'name.required' =>
                'Cell name is required.',

            'name.unique' =>
                'This cell already exists in the selected sector.',

            'name.max' =>
                'Cell name may not exceed 150 characters.',

            'is_active.boolean' =>
                'Cell status must be true or false.',
        ];
    }
}
