<?php

namespace App\Http\Requests\API\DirectFarmerDelivery;

use App\Models\DirectFarmerDelivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayDirectFarmerDeliveryRequest extends FormRequest
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
            'payment_method' => [
                'required',
                Rule::in(
                    DirectFarmerDelivery::PAYMENT_METHODS
                ),
            ],

            'payment_reference' => [
                'nullable',
                'required_if:payment_method,mobile_money,bank',
                'string',
                'max:120',
                'unique:direct_farmer_deliveries,payment_reference',
            ],
        ];
    }
}
