<?php

namespace App\Http\Requests\API\Worker;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkerRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' =>
                $this->filled('phone')
                    ? trim((string) $this->phone)
                    : null,

            'email' =>
                $this->filled('email')
                    ? trim((string) $this->email)
                    : null,

            'national_id' =>
                $this->filled('national_id')
                    ? trim((string) $this->national_id)
                    : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
                'unique:workers,phone',
            ],

            'email' => [
                'nullable',
                'email',
                'max:190',
                'unique:workers,email',
            ],

            'national_id' => [
                'nullable',
                'string',
                'max:50',
                'unique:workers,national_id',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
