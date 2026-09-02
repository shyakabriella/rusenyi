<?php

namespace App\Observers;

use App\Models\CoffeePurchase;
use App\Services\Finance\AgentWalletService;

class CoffeePurchaseObserver
{
    public function __construct(
        private AgentWalletService $wallet
    ) {
    }

    public function updated(CoffeePurchase $purchase): void
    {
        if (!$purchase->wasChanged('status')) {
            return;
        }

        if (
            $purchase->status ===
            CoffeePurchase::STATUS_APPROVED
        ) {
            $this->wallet->recordPurchase($purchase);

            return;
        }

        if (
            $purchase->status ===
            CoffeePurchase::STATUS_CANCELLED &&
            $purchase->approved_at
        ) {
            $this->wallet->recordPurchase($purchase);
            $this->wallet->reversePurchase($purchase);
        }
    }
}
