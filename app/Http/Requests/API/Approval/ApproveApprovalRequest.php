<?php

namespace App\Http\Requests\API\Approval;

use Illuminate\Foundation\Http\FormRequest;

class ApproveApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'review_note' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }
}
