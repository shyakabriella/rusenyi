<?php

namespace App\Http\Requests\API\PettyCash;

use App\Models\PettyCashTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePettyCashTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->role,
            ['admin', 'accountant'],
            true
        );
    }

    public function rules(): array
    {
        return [
            'transaction_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],

            'transaction_type' => [
                'required',
                Rule::in(
                    PettyCashTransaction::POSTABLE_TYPES
                ),
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'category' => [
                'nullable',
                'string',
                'max:100',
            ],

            'counterparty_name' => [
                'required',
                'string',
                'min:2',
                'max:150',
            ],

            'purpose' => [
                'required',
                'string',
                'min:3',
                'max:3000',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:150',
            ],

            'receipt_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }
}
