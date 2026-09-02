<?php

namespace App\Http\Requests\API\Finance\CashAllocation;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UploadPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && in_array(
                $user->role,
                [
                    User::ROLE_ADMIN,
                    User::ROLE_ACCOUNTANT,
                ],
                true
            );
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

    public function messages(): array
    {
        return [
            'payment_proof.required' =>
                'Payment proof is required.',

            'payment_proof.mimes' =>
                'Payment proof must be JPG, PNG or PDF.',

            'payment_proof.max' =>
                'Payment proof cannot be larger than 5 MB.',
        ];
    }
}
