<?php

namespace App\Http\Requests\API\Finance\CashAllocation;

use App\Models\Agent;
use App\Models\CashAllocation;
use App\Models\CoffeeSeason;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCashAllocationRequest extends FormRequest
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
        $allocation =
            $this->route(
                'cashAllocation'
            );

        $data = [];

        if (
            !$this->filled(
                'payment_method'
            )
        ) {
            $data['payment_method'] =
                $allocation instanceof
                CashAllocation
                    ? (
                        $allocation->payment_method
                        ?? CashAllocation::PAYMENT_METHOD_CASH
                    )
                    : CashAllocation::PAYMENT_METHOD_CASH;
        }

        foreach ([
            'reference',
            'purpose',
            'notes',
        ] as $field) {
            if (!$this->has($field)) {
                continue;
            }

            $value = trim(
                (string) $this->input(
                    $field
                )
            );

            $data[$field] =
                $value === ''
                    ? null
                    : $value;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $cashAllocation =
            $this->route(
                'cashAllocation'
            );

        $id =
            $cashAllocation instanceof
            CashAllocation
                ? $cashAllocation->id
                : $cashAllocation;

        return [
            'coffee_season_id' => [
                'required',
                'integer',
                'exists:coffee_seasons,id',
            ],

            'agent_id' => [
                'required',
                'integer',
                'exists:agents,id',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999999999.99',
            ],

            'payment_method' => [
                'required',

                Rule::in(
                    CashAllocation::PAYMENT_METHODS
                ),
            ],

            'reference' => [
                'nullable',

                'required_if:payment_method,mobile_money,bank_transfer',

                'string',
                'max:100',

                Rule::unique(
                    'cash_allocations',
                    'reference'
                )->ignore($id),
            ],

            'allocation_date' => [
                'required',
                'date',
            ],

            'purpose' => [
                'nullable',
                'string',
                'max:2000',
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
                    $this->filled(
                        'agent_id'
                    )
                ) {
                    $agent =
                        Agent::query()
                            ->with('user')
                            ->find(
                                $this->integer(
                                    'agent_id'
                                )
                            );

                    if ($agent) {
                        if (
                            $agent->status !==
                            Agent::STATUS_ACTIVE
                        ) {
                            $validator
                                ->errors()
                                ->add(
                                    'agent_id',
                                    'Cash can only be allocated to an active agent.'
                                );
                        } elseif (
                            !$agent->user ||
                            $agent->user->status !==
                                'active'
                        ) {
                            $validator
                                ->errors()
                                ->add(
                                    'agent_id',
                                    'The linked agent user account must be active.'
                                );
                        }
                    }
                }

                if (
                    $this->filled(
                        'coffee_season_id'
                    )
                ) {
                    $season =
                        CoffeeSeason::find(
                            $this->integer(
                                'coffee_season_id'
                            )
                        );

                    if (
                        $season &&
                        $season->status !==
                            CoffeeSeason::STATUS_ACTIVE
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                'coffee_season_id',
                                'Cash can only be allocated during an active coffee season.'
                            );
                    }
                }
            },
        ];
    }
}
