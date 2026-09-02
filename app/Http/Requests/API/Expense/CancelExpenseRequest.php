<?php

namespace App\Http\Requests\API\Expense;

use Illuminate\Foundation\Http\FormRequest;

class CancelExpenseRequest extends FormRequest
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
            'cancellation_reason' => [
                'required',
                'string',
                'min:3',
                'max:3000',
            ],
        ];
    }
}
