<?php

namespace App\Http\Requests\API\Transport;

use App\Models\Driver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in(
                    Driver::STATUSES
                ),
            ],
        ];
    }
}
