<?php

namespace App\Http\Requests\API\Agent;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'admin',
            'accountant',
        ]) ?? false;
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

            'assigned_location_id' => [
                'nullable',
                'integer',
                'exists:locations,id',
            ],

            'working_since' => [
                'nullable',
                'date',
                'before_or_equal:today',
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
            function (Validator $validator): void {
                if (!$this->filled('user_id')) {
                    return;
                }

                $user = User::find($this->integer('user_id'));

                if ($user === null) {
                    return;
                }

                if (!$user->hasRole('agent')) {
                    $validator->errors()->add(
                        'user_id',
                        'The selected user must have the agent role.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' =>
                'Please select the user who owns this agent profile.',

            'user_id.exists' =>
                'The selected user does not exist.',

            'user_id.unique' =>
                'This user already has an agent profile.',

            'assigned_location_id.exists' =>
                'The selected location does not exist.',

            'working_since.before_or_equal' =>
                'Working since cannot be a future date.',
        ];
    }
}
