<?php

namespace App\Http\Requests\API\Admin\Agent;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) {
            $notes = trim(
                (string) $this->input(
                    'notes'
                )
            );

            $this->merge([
                'notes' =>
                    $notes === ''
                        ? null
                        : $notes,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                'unique:agents,user_id',
            ],

            'home_village_id' => [
                'nullable',
                'integer',
                'exists:villages,id',
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
                        'user_id'
                    )
                ) {
                    return;
                }

                $user = User::find(
                    $this->integer(
                        'user_id'
                    )
                );

                if (!$user) {
                    return;
                }

                if (
                    $user->role !==
                    User::ROLE_AGENT
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'user_id',
                            'The selected user must have the Agent role.'
                        );

                    return;
                }

                if (
                    isset($user->status) &&
                    $user->status !==
                    'active'
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'user_id',
                            'The selected agent user must be active.'
                        );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' =>
                'Select an agent user.',

            'user_id.exists' =>
                'The selected user does not exist.',

            'user_id.unique' =>
                'This user already has an agent profile.',

            'home_village_id.exists' =>
                'The selected village does not exist.',
        ];
    }
}
