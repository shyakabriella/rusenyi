<?php

namespace App\Http\Requests\API\AgentCollection;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CancelAgentCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user &&
            in_array($user->role, [
                User::ROLE_ADMIN,
                User::ROLE_AGENT,
            ], true);
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => [
                'required',
                'string',
                'min:3',
                'max:2000',
            ],
        ];
    }
}
