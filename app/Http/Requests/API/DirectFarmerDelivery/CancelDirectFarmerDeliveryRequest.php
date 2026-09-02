<?php

namespace App\Http\Requests\API\DirectFarmerDelivery;

use Illuminate\Foundation\Http\FormRequest;

class CancelDirectFarmerDeliveryRequest extends FormRequest
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
            'cancellation_reason' => [
                'required',
                'string',
                'min:3',
                'max:2000',
            ],
        ];
    }
}
