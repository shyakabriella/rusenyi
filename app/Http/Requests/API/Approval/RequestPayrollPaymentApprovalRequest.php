<?php

namespace App\Http\Requests\API\Approval;

use Illuminate\Foundation\Http\FormRequest;

class RequestPayrollPaymentApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'accountant';
    }

    public function rules(): array
    {
        return [
            'request_note' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }
}
