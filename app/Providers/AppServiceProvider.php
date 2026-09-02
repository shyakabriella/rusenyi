<?php

namespace App\Providers;

use App\Models\CashAllocation;
use App\Models\CoffeePurchase;
use App\Observers\CashAllocationObserver;
use App\Observers\CoffeePurchaseObserver;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerAuditObservers();
        CashAllocation::observe(CashAllocationObserver::class);
        CoffeePurchase::observe(CoffeePurchaseObserver::class);
        //
    }

    private function registerAuditObservers(): void
    {
        $models = [
            \App\Models\ApprovalRequest::class,
            \App\Models\Payroll::class,
            \App\Models\Expense::class,
            \App\Models\PettyCashTransaction::class,
            \App\Models\CoffeePurchase::class,
            \App\Models\DirectFarmerDelivery::class,
            \App\Models\AgentCollection::class,
            \App\Models\FieldWeighing::class,
            \App\Models\CollectionTrip::class,
            \App\Models\FactoryReception::class,
            \App\Models\CoffeeLot::class,
            \App\Models\StoreInventory::class,
            \App\Models\StockMovement::class,
            \App\Models\ProcessingBatch::class,
        ];

        foreach ($models as $model) {
            if (!class_exists($model)) {
                continue;
            }

            $model::observe(
                \App\Observers\AuditObserver::class
            );
        }
    }

}
