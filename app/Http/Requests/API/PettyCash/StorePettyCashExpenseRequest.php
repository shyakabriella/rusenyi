<?php

namespace App\Http\Requests\API\PettyCash;

use Illuminate\Foundation\Http\FormRequest;

class StorePettyCashExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role ===
            'accountant';
    }

    public function rules(): array
    {
        return [
            'expense_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],

            'category' => [
                'required',
                'string',
                'max:120',
            ],

            'payee' => [
                'nullable',
                'string',
                'max:255',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'description' => [
                'required',
                'string',
                'min:3',
                'max:3000',
            ],

            'receipt' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ];
    }
}
