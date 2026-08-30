<?php

namespace App\Http\Requests\API\Admin\Location;

use App\Models\CollectionPoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectionPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('name')) {
            $data['name'] =
                trim((string) $this->input('name'));
        }

        if ($this->has('code')) {
            $data['code'] =
                strtoupper(
                    trim((string) $this->input('code'))
                );
        }

        if ($this->has('description')) {
            $description =
                trim((string) $this->input('description'));

            $data['description'] =
                $description !== ''
                    ? $description
                    : null;
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $collectionPoint =
            $this->route('collectionPoint')
            ?? $this->route('collection_point');

        $collectionPointId =
            $collectionPoint instanceof CollectionPoint
                ? $collectionPoint->id
                : $collectionPoint;

        return [
            'village_id' => [
                'required',
                'integer',
                'exists:villages,id',
            ],

            'name' => [
                'required',
                'string',
                'max:150',

                Rule::unique(
                    'collection_points',
                    'name'
                )
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'village_id',
                                $this->integer('village_id')
                            )
                    )
                    ->ignore($collectionPointId),
            ],

            'code' => [
                'required',
                'string',
                'max:50',

                Rule::unique(
                    'collection_points',
                    'code'
                )
                    ->ignore($collectionPointId),
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
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
            'village_id.required' =>
                'Village is required.',

            'village_id.exists' =>
                'The selected village does not exist.',

            'name.required' =>
                'Collection point name is required.',

            'name.unique' =>
                'This collection point already exists in the selected village.',

            'code.required' =>
                'Collection point code is required.',

            'code.unique' =>
                'This collection point code is already in use.',

            'latitude.numeric' =>
                'Latitude must be a valid number.',

            'latitude.between' =>
                'Latitude must be between -90 and 90.',

            'longitude.numeric' =>
                'Longitude must be a valid number.',

            'longitude.between' =>
                'Longitude must be between -180 and 180.',

            'description.max' =>
                'Description may not exceed 1000 characters.',

            'is_active.boolean' =>
                'Collection point status must be true or false.',
        ];
    }
}
