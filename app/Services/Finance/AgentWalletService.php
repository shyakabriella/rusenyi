<?php

namespace App\Services\Finance;

use App\Models\AgentWalletTransaction;
use App\Models\CashAllocation;
use App\Models\CoffeePurchase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AgentWalletService
{
    public function recordAllocation(
        CashAllocation $allocation
    ): AgentWalletTransaction {
        return AgentWalletTransaction::firstOrCreate(
            [
                'source_type' => 'cash_allocation',
                'source_id' => $allocation->id,
                'type' => AgentWalletTransaction::TYPE_CASH_ALLOCATION,
            ],
            [
                'agent_id' => $allocation->agent_id,
                'coffee_season_id' => $allocation->coffee_season_id,
                'direction' => AgentWalletTransaction::DIRECTION_CREDIT,
                'amount' => $allocation->amount,
                'currency' => $allocation->currency ?: 'RWF',
                'description' => "Cash allocation {$allocation->allocation_code}",
                'created_by' =>
                    $allocation->approved_by
                    ?? $allocation->created_by,
            ]
        );
    }

    public function reverseAllocation(
        CashAllocation $allocation
    ): AgentWalletTransaction {
        return AgentWalletTransaction::firstOrCreate(
            [
                'source_type' => 'cash_allocation',
                'source_id' => $allocation->id,
                'type' => AgentWalletTransaction::TYPE_CASH_ALLOCATION_REVERSAL,
            ],
            [
                'agent_id' => $allocation->agent_id,
                'coffee_season_id' => $allocation->coffee_season_id,
                'direction' => AgentWalletTransaction::DIRECTION_DEBIT,
                'amount' => $allocation->amount,
                'currency' => $allocation->currency ?: 'RWF',
                'description' => "Reversal of {$allocation->allocation_code}",
                'created_by' =>
                    $allocation->cancelled_by
                    ?? $allocation->created_by,
            ]
        );
    }

    public function recordPurchase(
        CoffeePurchase $purchase
    ): AgentWalletTransaction {
        return AgentWalletTransaction::firstOrCreate(
            [
                'source_type' => 'coffee_purchase',
                'source_id' => $purchase->id,
                'type' => AgentWalletTransaction::TYPE_COFFEE_PURCHASE,
            ],
            [
                'agent_id' => $purchase->agent_id,
                'coffee_season_id' => $purchase->coffee_season_id,
                'direction' => AgentWalletTransaction::DIRECTION_DEBIT,
                'amount' => $purchase->total_amount,
                'currency' => $purchase->currency ?: 'RWF',
                'description' => "Coffee purchase {$purchase->purchase_code}",
                'created_by' =>
                    $purchase->approved_by
                    ?? $purchase->created_by,
            ]
        );
    }

    public function reversePurchase(
        CoffeePurchase $purchase
    ): AgentWalletTransaction {
        return AgentWalletTransaction::firstOrCreate(
            [
                'source_type' => 'coffee_purchase',
                'source_id' => $purchase->id,
                'type' => AgentWalletTransaction::TYPE_COFFEE_PURCHASE_REVERSAL,
            ],
            [
                'agent_id' => $purchase->agent_id,
                'coffee_season_id' => $purchase->coffee_season_id,
                'direction' => AgentWalletTransaction::DIRECTION_CREDIT,
                'amount' => $purchase->total_amount,
                'currency' => $purchase->currency ?: 'RWF',
                'description' => "Reversal of {$purchase->purchase_code}",
                'created_by' =>
                    $purchase->cancelled_by
                    ?? $purchase->created_by,
            ]
        );
    }

    public function balance(
        int $agentId,
        ?int $seasonId = null
    ): float {
        $query = $this->query($agentId, $seasonId);

        $credits = (clone $query)
            ->where(
                'direction',
                AgentWalletTransaction::DIRECTION_CREDIT
            )
            ->sum('amount');

        $debits = (clone $query)
            ->where(
                'direction',
                AgentWalletTransaction::DIRECTION_DEBIT
            )
            ->sum('amount');

        return (float) $credits - (float) $debits;
    }

    public function summary(
        int $agentId,
        ?int $seasonId = null
    ): array {
        $query = $this->query($agentId, $seasonId);

        $allocated = (clone $query)
            ->where(
                'type',
                AgentWalletTransaction::TYPE_CASH_ALLOCATION
            )
            ->sum('amount');

        $reversed = (clone $query)
            ->where(
                'type',
                AgentWalletTransaction::TYPE_CASH_ALLOCATION_REVERSAL
            )
            ->sum('amount');

        $spent = (clone $query)
            ->where(
                'type',
                AgentWalletTransaction::TYPE_COFFEE_PURCHASE
            )
            ->sum('amount');

        $purchaseReversals = (clone $query)
            ->where(
                'type',
                AgentWalletTransaction::TYPE_COFFEE_PURCHASE_REVERSAL
            )
            ->sum('amount');

        return [
            'total_allocated' => $this->money($allocated),
            'total_reversed' => $this->money($reversed),
            'total_spent' => $this->money($spent),
            'purchase_reversals' => $this->money($purchaseReversals),
            'balance' => $this->money(
                $this->balance($agentId, $seasonId)
            ),
            'currency' => 'RWF',
        ];
    }

    public function syncAllAllocations(): int
    {
        $count = 0;

        CashAllocation::query()
            ->whereNotNull('approved_at')
            ->whereIn('status', [
                CashAllocation::STATUS_APPROVED,
                CashAllocation::STATUS_CANCELLED,
            ])
            ->orderBy('id')
            ->chunkById(100, function ($allocations) use (&$count) {
                foreach ($allocations as $allocation) {
                    $this->recordAllocation($allocation);

                    if (
                        $allocation->status ===
                        CashAllocation::STATUS_CANCELLED
                    ) {
                        $this->reverseAllocation($allocation);
                    }

                    $count++;
                }
            });

        return $count;
    }

    private function query(
        int $agentId,
        ?int $seasonId
    ): Builder {
        return AgentWalletTransaction::query()
            ->where('agent_id', $agentId)
            ->when(
                $seasonId,
                fn (Builder $query) =>
                    $query->where(
                        'coffee_season_id',
                        $seasonId
                    )
            );
    }

    private function money($amount): string
    {
        return number_format(
            (float) $amount,
            2,
            '.',
            ''
        );
    }
}
