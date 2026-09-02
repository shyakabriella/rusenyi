<?php

namespace App\Http\Requests\API\CoffeePurchase;

use App\Models\CoffeePrice;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoffeePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user &&
            in_array($user->role, [
                User::ROLE_ADMIN,
                User::ROLE_ACCOUNTANT,
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

            'farmer_id' => [
                'required',
                'integer',
                'exists:farmers,id',
            ],

            'collection_point_id' => [
                'nullable',
                'integer',
                'exists:collection_points,id',
            ],

            'coffee_type' => [
                'required',
                Rule::in(CoffeePrice::COFFEE_TYPES),
            ],

            'quantity_kg' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999.99',
            ],

            'purchase_date' => [
                'required',
                'date',
            ],

            'purpose' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
