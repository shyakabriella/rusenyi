<?php

namespace App\Http\Requests\API\StoreInventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreInventoryRequest extends FormRequest
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
            'storage_location' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'bag_count' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }
}
