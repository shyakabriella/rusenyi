<?php

namespace App\Services;

use App\Models\PettyCashTransaction;

class PettyCashService
{
    public function balance(
        ?int $accountantId = null
    ): float {
        $query =
            PettyCashTransaction::query();

        if ($accountantId) {
            $query->where(
                'accountant_id',
                $accountantId
            );
        }

        return (float) (
            $query
                ->selectRaw("
                    COALESCE(
                        SUM(
                            CASE
                                WHEN transaction_type IN (
                                    'credit',
                                    'reversal'
                                )
                                    THEN amount
                                WHEN transaction_type = 'debit'
                                    THEN -amount
                                ELSE 0
                            END
                        ),
                        0
                    ) AS balance
                ")
                ->value('balance')
            ?? 0
        );
    }

    public function lockedBalanceFor(
        int $accountantId
    ): float {
        $transactions =
            PettyCashTransaction::query()
                ->where(
                    'accountant_id',
                    $accountantId
                )
                ->lockForUpdate()
                ->get([
                    'transaction_type',
                    'amount',
                ]);

        return $transactions->reduce(
            function (
                float $balance,
                PettyCashTransaction $transaction
            ) {
                $amount =
                    (float) $transaction->amount;

                if (
                    $transaction->transaction_type ===
                    PettyCashTransaction::TYPE_DEBIT
                ) {
                    return $balance - $amount;
                }

                if (
                    in_array(
                        $transaction->transaction_type,
                        [
                            PettyCashTransaction::TYPE_CREDIT,
                            PettyCashTransaction::TYPE_REVERSAL,
                        ],
                        true
                    )
                ) {
                    return $balance + $amount;
                }

                return $balance;
            },
            0.0
        );
    }
}
