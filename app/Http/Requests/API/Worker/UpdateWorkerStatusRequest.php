<?php

namespace App\Http\Requests\API\Worker;

use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->role,
            [
                'admin',
                'store',
            ],
            true
        );
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in(
                    Worker::STATUSES
                ),
            ],
        ];
    }
}
