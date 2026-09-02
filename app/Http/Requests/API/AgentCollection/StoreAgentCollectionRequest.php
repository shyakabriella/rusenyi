<?php

namespace App\Http\Requests\API\AgentCollection;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgentCollectionRequest extends FormRequest
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
            'agent_id' => [
                'nullable',
                'integer',
                'exists:agents,id',
            ],

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

            'purchase_ids' => [
                'nullable',
                'array',
                'max:200',
            ],

            'purchase_ids.*' => [
                'integer',
                'distinct',
                'exists:coffee_purchases,id',
            ],
        ];
    }
}
