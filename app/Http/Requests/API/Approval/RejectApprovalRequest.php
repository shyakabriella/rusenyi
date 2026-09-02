<?php

namespace App\Http\Requests\API\Approval;

use Illuminate\Foundation\Http\FormRequest;

class RejectApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'review_note' => [
                'required',
                'string',
                'min:3',
                'max:3000',
            ],
        ];
    }
}
