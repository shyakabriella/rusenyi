<?php

namespace App\Http\Requests\API\StockMovement;

use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
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
            'store_inventory_id' => [
                'required',
                'integer',
                'exists:store_inventories,id',
            ],

            'movement_type' => [
                'required',
                Rule::in(
                    StockMovement::MANUAL_TYPES
                ),
            ],

            'quantity_kg' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'to_location' => [
                'nullable',
                'string',
                'max:150',
            ],

            'reference_type' => [
                'nullable',
                'string',
                'max:80',
            ],

            'reference_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'reason' => [
                'required',
                'string',
                'min:3',
                'max:3000',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }
}
