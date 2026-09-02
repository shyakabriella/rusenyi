<?php

namespace App\Http\Requests\API\DirectFarmerDelivery;

use Illuminate\Foundation\Http\FormRequest;

class UploadDirectFarmerPaymentProofRequest extends FormRequest
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
            'payment_proof' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ];
    }
}
