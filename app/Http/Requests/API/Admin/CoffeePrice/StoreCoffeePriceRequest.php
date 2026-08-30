<?php

namespace App\Http\Requests\API\Admin\CoffeePrice;

use App\Models\CoffeePrice;
use App\Models\CoffeeSeason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCoffeePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('coffee_type')) {
            $data['coffee_type'] =
                strtolower(
                    trim(
                        (string)
                        $this->input(
                            'coffee_type'
                        )
                    )
                );
        }

        if ($this->has('notes')) {
            $notes = trim(
                (string)
                $this->input('notes')
            );

            $data['notes'] =
                $notes === ''
                    ? null
                    : $notes;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'coffee_season_id' => [
                'required',
                'integer',
                'exists:coffee_seasons,id',
            ],

            'coffee_type' => [
                'required',
                'string',
                Rule::in(
                    CoffeePrice::COFFEE_TYPES
                ),
            ],

            'price_per_kg' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999999.99',
            ],

            'effective_from' => [
                'required',
                'date',
            ],

            'effective_to' => [
                'nullable',
                'date',
                'after_or_equal:effective_from',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (
                Validator $validator
            ): void {
                if (
                    !$this->filled(
                        'coffee_season_id'
                    )
                ) {
                    return;
                }

                $season =
                    CoffeeSeason::find(
                        $this->integer(
                            'coffee_season_id'
                        )
                    );

                if (!$season) {
                    return;
                }

                if (
                    $season->status ===
                    CoffeeSeason::STATUS_CLOSED
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'coffee_season_id',
                            'New prices cannot be added to a closed coffee season.'
                        );

                    return;
                }

                if (
                    $this->filled(
                        'effective_from'
                    )
                ) {
                    $from =
                        $this->date(
                            'effective_from'
                        );

                    if (
                        $from &&
                        $from->lt(
                            $season->start_date
                        )
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                'effective_from',
                                'Effective date cannot be before the coffee season start date.'
                            );
                    }

                    if (
                        $from &&
                        $season->end_date &&
                        $from->gt(
                            $season->end_date
                        )
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                'effective_from',
                                'Effective date cannot be after the coffee season end date.'
                            );
                    }
                }

                if (
                    $this->filled(
                        'effective_to'
                    ) &&
                    $season->end_date
                ) {
                    $to =
                        $this->date(
                            'effective_to'
                        );

                    if (
                        $to &&
                        $to->gt(
                            $season->end_date
                        )
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                'effective_to',
                                'Price end date cannot be after the coffee season end date.'
                            );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'coffee_season_id.required' =>
                'Coffee season is required.',

            'coffee_season_id.exists' =>
                'The selected coffee season does not exist.',

            'coffee_type.required' =>
                'Coffee type is required.',

            'coffee_type.in' =>
                'The selected coffee type is invalid.',

            'price_per_kg.required' =>
                'Price per KG is required.',

            'price_per_kg.numeric' =>
                'Price per KG must be a valid number.',

            'price_per_kg.gt' =>
                'Price per KG must be greater than zero.',

            'effective_from.required' =>
                'Effective start date is required.',

            'effective_to.after_or_equal' =>
                'Price end date cannot be before the effective start date.',
        ];
    }
}
