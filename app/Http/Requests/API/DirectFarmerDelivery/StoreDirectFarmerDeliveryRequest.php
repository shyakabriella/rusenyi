<?php

namespace App\Http\Requests\API\DirectFarmerDelivery;

use App\Models\CoffeePrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDirectFarmerDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() &&
            in_array($this->user()->role, [
                'admin',
                'accountant',
                'balance',
            ], true);
    }

    public function rules(): array
    {
        return [
            'farmer_id' => [
                'required',
                'integer',
                'exists:farmers,id',
            ],

            'balance_officer_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'coffee_type' => [
                'required',
                Rule::in(CoffeePrice::COFFEE_TYPES),
            ],

            'quantity_kg' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'delivery_date' => [
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
