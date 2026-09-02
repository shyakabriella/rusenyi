<?php

namespace App\Http\Requests\API\StoreInventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreStoreInventoryRequest extends FormRequest
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
            'coffee_lot_id' => [
                'required',
                'integer',
                'exists:coffee_lots,id',
            ],

            'storage_location' => [
                'required',
                'string',
                'max:150',
            ],

            'bag_count' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'received_at' => [
                'nullable',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }
}
