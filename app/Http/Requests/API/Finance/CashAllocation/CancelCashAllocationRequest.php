<?php

namespace App\Http\Requests\API\Finance\CashAllocation;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CancelCashAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && in_array(
                $user->role,
                [
                    User::ROLE_ADMIN,
                    User::ROLE_ACCOUNTANT,
                ],
                true
            );
    }

    protected function prepareForValidation(): void
    {
        if (
            $this->has(
                'cancellation_reason'
            )
        ) {
            $this->merge([
                'cancellation_reason' =>
                    trim(
                        (string)
                        $this->input(
                            'cancellation_reason'
                        )
                    ),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => [
                'required',
                'string',
                'min:3',
                'max:2000',
            ],
        ];
    }
}
