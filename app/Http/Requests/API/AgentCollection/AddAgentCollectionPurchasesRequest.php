<?php

namespace App\Http\Requests\API\AgentCollection;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AddAgentCollectionPurchasesRequest extends FormRequest
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
            'purchase_ids' => [
                'required',
                'array',
                'min:1',
                'max:200',
            ],

            'purchase_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:coffee_purchases,id',
            ],
        ];
    }
}
