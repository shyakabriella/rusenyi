<?php

namespace App\Observers;

use App\Models\CashAllocation;
use App\Services\Finance\AgentWalletService;

class CashAllocationObserver
{
    public function __construct(
        private AgentWalletService $wallet
    ) {
    }

    public function updated(CashAllocation $allocation): void
    {
        if (!$allocation->wasChanged('status')) {
            return;
        }

        if ($allocation->status === CashAllocation::STATUS_APPROVED) {
            $this->wallet->recordAllocation($allocation);

            return;
        }

        if (
            $allocation->status === CashAllocation::STATUS_CANCELLED &&
            $allocation->approved_at
        ) {
            $this->wallet->recordAllocation($allocation);
            $this->wallet->reverseAllocation($allocation);
        }
    }
}
