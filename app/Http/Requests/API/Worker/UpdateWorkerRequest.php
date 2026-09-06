<?php

namespace App\Http\Requests\API\Worker;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->role,
            [
                'admin',
                'store',
            ],
            true
        );
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'phone',
            'email',
            'national_id',
        ] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field =>
                        $this->filled($field)
                            ? trim(
                                (string) $this->input($field)
                            )
                            : null,
                ]);
            }
        }
    }

    public function rules(): array
    {
        $worker =
            $this->route('worker');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',

                Rule::unique(
                    'workers',
                    'phone'
                )->ignore($worker?->id),
            ],

            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:190',

                Rule::unique(
                    'workers',
                    'email'
                )->ignore($worker?->id),
            ],

            'national_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',

                Rule::unique(
                    'workers',
                    'national_id'
                )->ignore($worker?->id),
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
