<?php

namespace App\Http\Requests\API\CoffeePurchase;

use Illuminate\Foundation\Http\FormRequest;

class CancelCoffeePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
