<?php

namespace App\Http\Requests\API\Expense;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
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
            'expense_date' => [
                'sometimes',
                'required',
                'date',
                'before_or_equal:today',
            ],

            'category' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:100',
            ],

            'payee_name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:150',
            ],

            'description' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:3000',
            ],

            'amount' => [
                'sometimes',
                'required',
                'numeric',
                'gt:0',
            ],

            'payment_method' => [
                'sometimes',
                'required',
                Rule::in(
                    Expense::PAYMENT_METHODS
                ),
            ],

            'payment_reference' => [
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
