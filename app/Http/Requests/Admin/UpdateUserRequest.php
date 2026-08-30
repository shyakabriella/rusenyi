<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($user?->id),
            ],

            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('users', 'phone')
                    ->ignore($user?->id),
            ],

            'role' => [
                'sometimes',
                'required',
                Rule::in(User::roles()),
                Rule::exists('roles', 'name')
                    ->where('is_active', true),
            ],
        ];
    }
}
