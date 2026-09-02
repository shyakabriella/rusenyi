<?php

namespace App\Http\Requests\API\StockMovement;

use Illuminate\Foundation\Http\FormRequest;

class ReverseStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->role,
            ['admin', 'store'],
            true
        );
    }

    public function rules(): array
    {
        return [
            'reversal_reason' => [
                'required',
                'string',
                'min:3',
                'max:3000',
            ],
        ];
    }
}
