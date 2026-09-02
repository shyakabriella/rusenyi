<?php

namespace App\Http\Requests\API\Admin\Farmer;

use App\Models\CollectionPoint;
use App\Models\Farmer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFarmerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach ([
            'full_name',
            'phone',
            'national_id',
            'address_note',
            'notes',
        ] as $field) {
            if (!$this->has($field)) {
                continue;
            }

            $value = trim(
                (string) $this->input($field)
            );

            $data[$field] =
                $value === ''
                    ? null
                    : $value;
        }

        if ($this->has('gender')) {
            $gender = strtolower(
                trim(
                    (string) $this->input(
                        'gender'
                    )
                )
            );

            $data['gender'] =
                $gender === ''
                    ? null
                    : $gender;
        }

        if (
            $this->has(
                'preferred_payment_method'
            )
        ) {
            $data[
                'preferred_payment_method'
            ] = strtolower(
                trim(
                    (string) $this->input(
                        'preferred_payment_method'
                    )
                )
            );
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'full_name' => [
                'required',
                'string',
                'max:150',
            ],

            'phone' => [
                'required',
                'string',
                'max:30',
                'regex:/^\+?[0-9]{9,15}$/',
                Rule::unique(
                    'farmers',
                    'phone'
                ),
            ],

            'national_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique(
                    'farmers',
                    'national_id'
                ),
            ],

            'gender' => [
                'nullable',
                Rule::in(
                    Farmer::GENDERS
                ),
            ],

            'village_id' => [
                'required',
                'integer',
                'exists:villages,id',
            ],

            'collection_point_id' => [
                'nullable',
                'integer',
                'exists:collection_points,id',
            ],

            'preferred_payment_method' => [
                'nullable',
                Rule::in(
                    Farmer::PAYMENT_METHODS
                ),
            ],

            'address_note' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (
                Validator $validator
            ): void {
                if (
                    !$this->filled(
                        'collection_point_id'
                    ) ||
                    !$this->filled(
                        'village_id'
                    )
                ) {
                    return;
                }

                $collectionPoint =
                    CollectionPoint::find(
                        $this->integer(
                            'collection_point_id'
                        )
                    );

                if (
                    $collectionPoint &&
                    (int)
                    $collectionPoint->village_id !==
                    $this->integer(
                        'village_id'
                    )
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'collection_point_id',
                            'The selected collection point does not belong to the selected village.'
                        );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' =>
                'Farmer name is required.',

            'phone.required' =>
                'Farmer phone number is required.',

            'phone.regex' =>
                'Enter a valid farmer phone number.',

            'phone.unique' =>
                'A farmer with this phone number already exists.',

            'national_id.unique' =>
                'A farmer with this National ID already exists.',

            'village_id.required' =>
                'Farmer village is required.',

            'village_id.exists' =>
                'The selected village does not exist.',

            'collection_point_id.exists' =>
                'The selected collection point does not exist.',
        ];
    }
}
