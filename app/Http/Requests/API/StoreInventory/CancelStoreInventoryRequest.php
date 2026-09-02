<?php

namespace App\Http\Requests\API\StoreInventory;

use Illuminate\Foundation\Http\FormRequest;

class CancelStoreInventoryRequest extends FormRequest
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
            'cancellation_reason' => [
                'required',
                'string',
                'min:3',
                'max:3000',
            ],
        ];
    }
}
