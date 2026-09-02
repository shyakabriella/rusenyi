<?php

namespace App\Http\Requests\API\Payroll;

use App\Models\Payroll;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayPayrollRequest extends FormRequest
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
            'payment_method' => [
                'required',
                Rule::in(
                    Payroll::PAYMENT_METHODS
                ),
            ],

            'payment_reference' => [
                'nullable',
                'string',
                'max:150',
            ],

            'payment_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
        ];
    }
}
