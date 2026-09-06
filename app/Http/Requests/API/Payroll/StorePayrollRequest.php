<?php

namespace App\Http\Requests\API\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->role,
            [
                'admin',
                'accountant',
                'store',
            ],
            true
        );
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                'integer',
                'exists:workers,id',
            ],

            'payroll_month' => [
                'required',
                'string',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
            ],

            'basic_salary' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'allowances' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'deductions' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }
}
