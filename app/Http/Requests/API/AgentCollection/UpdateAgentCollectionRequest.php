<?php

namespace App\Http\Requests\API\AgentCollection;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentCollectionRequest extends FormRequest
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
            'collection_point_id' => [
                'nullable',
                'integer',
                'exists:collection_points,id',
            ],

            'collection_date' => [
                'required',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
