<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    public function overview(string $viewerRole): array
    {
        $today = today();
        $yesterday = today()->subDay();

        $receivedToday = $this->coffeeReceivedOn($today);
        $receivedYesterday = $this->coffeeReceivedOn($yesterday);

        $purchasedToday = $this->coffeePurchasedOn($today);
        $purchasedYesterday = $this->coffeePurchasedOn($yesterday);

        $moneyUsedToday = $this->moneyUsedOn($today);
        $moneyUsedYesterday = $this->moneyUsedOn($yesterday);

        $availableCash = $this->availableOperationalCash();

        return [
            'viewer_role' => $viewerRole,
            'generated_at' => now()->toISOString(),
            'currency' => 'RWF',

            'cards' => [
                'coffee_received_today' => [
                    'value' => $receivedToday,
                    'unit' => 'KG',
                    'change_percent' => $this->changePercent(
                        $receivedToday,
                        $receivedYesterday
                    ),
                ],

                'coffee_purchased_today' => [
                    'value' => $purchasedToday,
                    'unit' => 'KG',
                    'change_percent' => $this->changePercent(
                        $purchasedToday,
                        $purchasedYesterday
                    ),
                ],

                'money_used_today' => [
                    'value' => $moneyUsedToday,
                    'unit' => 'RWF',
                    'change_percent' => $this->changePercent(
                        $moneyUsedToday,
                        $moneyUsedYesterday
                    ),
                ],

                'available_cash' => [
                    'value' => $availableCash['total'],
                    'unit' => 'RWF',
                    'change_percent' => null,
                    'scope' => 'agent_wallets_and_petty_cash',
                ],
            ],

            'operations' => [
                'active_agents' => $this->activeAgents(),
                'farmers_served_today' => $this->farmersServedOn($today),
                'trips_today' => $this->tripsOn($today),
                'store_stock_kg' => $this->currentStoreStock(),
                'pending_approvals' => $this->pendingApprovals(),
            ],

            'finance' => [
                'coffee_purchase_spending_today' =>
                    $this->coffeePurchaseSpendingOn($today),

                'direct_farmer_payments_today' =>
                    $this->directFarmerPaymentsOn($today),

                'expenses_today' =>
                    $this->expensesOn($today),

                'agent_wallet_balance' =>
                    $availableCash['agent_wallet_balance'],

                'petty_cash_balance' =>
                    $availableCash['petty_cash_balance'],

                'available_operational_cash' =>
                    $availableCash['total'],
            ],

            'weekly_received' => $this->weeklyReceived(),

            'source_breakdown' => $this->sourceBreakdown($today),

            'recent_activities' => $viewerRole === 'admin'
                ? $this->recentActivities()
                : [],

            'top_agents' => $this->topAgents(),

            'coffee_operations' => $this->coffeeOperations(),
        ];
    }

    private function coffeeReceivedOn(Carbon $date): float
    {
        return round(
            (float) DB::table('store_inventories')
                ->whereDate(
                    'received_at',
                    $date->toDateString()
                )
                ->where('status', '!=', 'cancelled')
                ->sum('initial_quantity_kg'),
            2
        );
    }

    private function coffeePurchasedOn(Carbon $date): float
    {
        $agentCoffee = (float) DB::table('coffee_purchases')
            ->whereDate(
                'purchase_date',
                $date->toDateString()
            )
            ->where('status', 'approved')
            ->sum('quantity_kg');

        $directCoffee = (float) DB::table(
            'direct_farmer_deliveries'
        )
            ->whereDate(
                'delivery_date',
                $date->toDateString()
            )
            ->where('status', 'confirmed')
            ->sum('quantity_kg');

        return round(
            $agentCoffee + $directCoffee,
            2
        );
    }

    private function moneyUsedOn(Carbon $date): float
    {
        $purchases = $this->coffeePurchaseSpendingOn($date);
        $directPayments = $this->directFarmerPaymentsOn($date);
        $expenses = $this->expensesOn($date);

        return round(
            $purchases + $directPayments + $expenses,
            2
        );
    }

    private function coffeePurchaseSpendingOn(
        Carbon $date
    ): float {
        return round(
            (float) DB::table('coffee_purchases')
                ->whereDate(
                    'purchase_date',
                    $date->toDateString()
                )
                ->where('status', 'approved')
                ->sum('total_amount'),
            2
        );
    }

    private function directFarmerPaymentsOn(
        Carbon $date
    ): float {
        return round(
            (float) DB::table(
                'direct_farmer_deliveries'
            )
                ->whereDate(
                    'paid_at',
                    $date->toDateString()
                )
                ->where('payment_status', 'paid')
                ->sum('total_amount'),
            2
        );
    }

    private function expensesOn(Carbon $date): float
    {
        return round(
            (float) DB::table('expenses')
                ->whereDate(
                    'expense_date',
                    $date->toDateString()
                )
                ->where('status', 'recorded')
                ->sum('amount'),
            2
        );
    }

    private function activeAgents(): int
    {
        return DB::table('users')
            ->where('role', 'agent')
            ->where('is_active', true)
            ->where('status', 'active')
            ->count();
    }

    private function farmersServedOn(Carbon $date): int
    {
        $agentFarmers = DB::table('coffee_purchases')
            ->whereDate(
                'purchase_date',
                $date->toDateString()
            )
            ->where('status', 'approved')
            ->whereNotNull('farmer_id')
            ->pluck('farmer_id');

        $directFarmers = DB::table(
            'direct_farmer_deliveries'
        )
            ->whereDate(
                'delivery_date',
                $date->toDateString()
            )
            ->where('status', 'confirmed')
            ->whereNotNull('farmer_id')
            ->pluck('farmer_id');

        return $agentFarmers
            ->merge($directFarmers)
            ->unique()
            ->count();
    }

    private function tripsOn(Carbon $date): int
    {
        return DB::table('collection_trips')
            ->whereDate(
                'created_at',
                $date->toDateString()
            )
            ->where('status', '!=', 'cancelled')
            ->count();
    }

    private function currentStoreStock(): float
    {
        return round(
            (float) DB::table('store_inventories')
                ->where('status', '!=', 'cancelled')
                ->sum('current_quantity_kg'),
            2
        );
    }

    private function pendingApprovals(): int
    {
        return DB::table('approval_requests')
            ->where('status', 'pending')
            ->count();
    }

    private function weeklyReceived(): array
    {
        $days = [];

        for ($offset = 6; $offset >= 0; $offset--) {
            $date = today()->subDays($offset);

            $days[] = [
                'date' => $date->toDateString(),
                'day' => $date->format('D'),
                'label' => $date->format('d M'),
                'kg' => $this->coffeeReceivedOn($date),
            ];
        }

        return $days;
    }

    private function sourceBreakdown(Carbon $date): array
    {
        $fromAgents = (float) DB::table('coffee_purchases')
            ->whereDate(
                'purchase_date',
                $date->toDateString()
            )
            ->where('status', 'approved')
            ->sum('quantity_kg');

        $fromFarmers = (float) DB::table(
            'direct_farmer_deliveries'
        )
            ->whereDate(
                'delivery_date',
                $date->toDateString()
            )
            ->where('status', 'confirmed')
            ->sum('quantity_kg');

        return [
            [
                'name' => 'From Agents',
                'value' => round($fromAgents, 2),
            ],
            [
                'name' => 'From Farmers',
                'value' => round($fromFarmers, 2),
            ],
        ];
    }

    private function availableOperationalCash(): array
    {
        $agentWalletBalance = 0.0;
        $pettyCashBalance = 0.0;

        if (
            Schema::hasTable('agent_wallet_transactions')
            && Schema::hasColumn(
                'agent_wallet_transactions',
                'direction'
            )
            && Schema::hasColumn(
                'agent_wallet_transactions',
                'amount'
            )
        ) {
            $credits = (float) DB::table(
                'agent_wallet_transactions'
            )
                ->where('direction', 'credit')
                ->sum('amount');

            $debits = (float) DB::table(
                'agent_wallet_transactions'
            )
                ->where('direction', 'debit')
                ->sum('amount');

            $agentWalletBalance = $credits - $debits;
        }

        if (
            Schema::hasTable('petty_cash_transactions')
            && Schema::hasColumn(
                'petty_cash_transactions',
                'balance_after'
            )
        ) {
            $pettyCashBalance = (float) (
                DB::table('petty_cash_transactions')
                    ->orderByDesc('id')
                    ->value('balance_after')
                ?? 0
            );
        }

        return [
            'agent_wallet_balance' =>
                round($agentWalletBalance, 2),

            'petty_cash_balance' =>
                round($pettyCashBalance, 2),

            'total' => round(
                $agentWalletBalance + $pettyCashBalance,
                2
            ),
        ];
    }

    private function recentActivities(): array
    {
        if (!Schema::hasTable('audit_logs')) {
            return [];
        }

        $availableColumns =
            Schema::getColumnListing('audit_logs');

        $wantedColumns = [
            'id',
            'audit_code',
            'action',
            'module',
            'description',
            'reference_code',
            'user_name',
            'created_at',
        ];

        $columns = array_values(
            array_intersect(
                $wantedColumns,
                $availableColumns
            )
        );

        if (!in_array('id', $columns, true)) {
            return [];
        }

        return DB::table('audit_logs')
            ->select($columns)
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function ($activity): array {
                return [
                    'id' => $activity->id,

                    'code' =>
                        $activity->audit_code
                        ?? null,

                    'title' =>
                        $this->activityTitle(
                            $activity->action
                            ?? null,
                            $activity->module
                            ?? null
                        ),

                    'description' =>
                        $activity->description
                        ?? $activity->reference_code
                        ?? 'System activity recorded.',

                    'module' =>
                        $activity->module
                        ?? null,

                    'action' =>
                        $activity->action
                        ?? null,

                    'user_name' =>
                        $activity->user_name
                        ?? null,

                    'created_at' =>
                        $activity->created_at
                        ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function topAgents(): array
    {
        if (
            !Schema::hasTable('coffee_purchases')
            || !Schema::hasTable('agents')
        ) {
            return [];
        }

        return DB::table('coffee_purchases as purchases')
            ->join(
                'agents',
                'agents.id',
                '=',
                'purchases.agent_id'
            )
            ->join(
                'users',
                'users.id',
                '=',
                'agents.user_id'
            )
            ->where('purchases.status', 'approved')
            ->where('users.is_active', true)
            ->where('users.status', 'active')
            ->select([
                'agents.id as agent_id',
                'users.name',
            ])
            ->selectRaw(
                'SUM(purchases.quantity_kg) as quantity_kg'
            )
            ->selectRaw(
                'SUM(purchases.total_amount) as total_amount'
            )
            ->selectRaw(
                'COUNT(DISTINCT purchases.agent_collection_id) as collections'
            )
            ->groupBy(
                'agents.id',
                'users.name'
            )
            ->orderByDesc('quantity_kg')
            ->limit(5)
            ->get()
            ->map(function ($agent): array {
                return [
                    'agent_id' => (int) $agent->agent_id,
                    'name' => $agent->name,
                    'quantity_kg' =>
                        round(
                            (float) $agent->quantity_kg,
                            2
                        ),
                    'total_amount' =>
                        round(
                            (float) $agent->total_amount,
                            2
                        ),
                    'collections' =>
                        (int) $agent->collections,
                ];
            })
            ->values()
            ->all();
    }

    private function coffeeOperations(): array
    {
        $today = today()->toDateString();

        return [
            'coffee_purchases' => [
                'value' => $this->approvedCoffeeForActiveSeason(),
                'unit' => 'KG',
                'note' => 'Purchased this season',
            ],

            'direct_farmer_deliveries' => [
                'value' => round(
                    (float) DB::table('direct_farmer_deliveries')
                        ->whereDate('delivery_date', $today)
                        ->where('status', 'confirmed')
                        ->sum('quantity_kg'),
                    2
                ),
                'unit' => 'KG',
                'note' => 'Delivered today',
            ],

            'agent_collections' => [
                'value' => DB::table('agent_collections')
                    ->whereDate('collection_date', $today)
                    ->where('status', '!=', 'cancelled')
                    ->count(),
                'unit' => '',
                'note' => 'Collections today',
            ],

            'field_weighings' => [
                'value' => $this->sumTodayByPossibleColumn(
                    'field_weighings',
                    ['field_weight_kg', 'weight_kg', 'quantity_kg'],
                    ['weighing_date', 'created_at']
                ),
                'unit' => 'KG',
                'note' => 'Field weighed today',
            ],

            'collection_trips' => [
                'value' => DB::table('collection_trips')
                    ->whereDate('created_at', $today)
                    ->where('status', '!=', 'cancelled')
                    ->count(),
                'unit' => '',
                'note' => 'Trips today',
            ],

            'factory_receptions' => [
                'value' => $this->sumTodayByPossibleColumn(
                    'factory_receptions',
                    [
                        'factory_weight_kg',
                        'received_quantity_kg',
                        'quantity_kg',
                    ],
                    ['received_at', 'reception_date', 'created_at']
                ),
                'unit' => 'KG',
                'note' => 'Received today',
            ],

            'weight_reconciliations' => [
                'value' => $this->pendingCount(
                    'weight_reconciliations'
                ),
                'unit' => '',
                'note' => 'Pending reconciliation',
            ],

            'coffee_lots' => [
                'value' => $this->activeCount(
                    'coffee_lots'
                ),
                'unit' => '',
                'note' => 'Active lots',
            ],

            'store_inventories' => [
                'value' => round(
                    (float) DB::table('store_inventories')
                        ->where('status', '!=', 'cancelled')
                        ->sum('current_quantity_kg'),
                    2
                ),
                'unit' => 'KG',
                'note' => 'Current stock',
            ],

            'stock_movements' => [
                'value' => $this->todayCount(
                    'stock_movements'
                ),
                'unit' => '',
                'note' => 'Movements today',
            ],

            'processing_batches' => [
                'value' => $this->activeCount(
                    'processing_batches'
                ),
                'unit' => '',
                'note' => 'Active batches',
            ],

            'processing_yield' => [
                'value' => $this->averagePossibleColumn(
                    'processing_batches',
                    [
                        'yield_percentage',
                        'yield_percent',
                        'yield_rate',
                    ]
                ),
                'unit' => '%',
                'note' => 'Average yield',
            ],

            'flow' => [
                'purchased_kg' => round(
                    (float) DB::table('coffee_purchases')
                        ->whereDate('purchase_date', $today)
                        ->where('status', 'approved')
                        ->sum('quantity_kg'),
                    2
                ),

                'collected' => DB::table('agent_collections')
                    ->whereDate('collection_date', $today)
                    ->where('status', '!=', 'cancelled')
                    ->count(),

                'transported_kg' =>
                    $this->sumTodayByPossibleColumn(
                        'collection_trips',
                        ['field_weight_kg'],
                        ['departure_at', 'created_at']
                    ),

                'factory_received_kg' =>
                    $this->sumTodayByPossibleColumn(
                        'factory_receptions',
                        [
                            'factory_weight_kg',
                            'received_quantity_kg',
                            'quantity_kg',
                        ],
                        [
                            'received_at',
                            'reception_date',
                            'created_at',
                        ]
                    ),

                'stored_kg' => round(
                    (float) DB::table('store_inventories')
                        ->whereDate('received_at', $today)
                        ->where('status', '!=', 'cancelled')
                        ->sum('initial_quantity_kg'),
                    2
                ),
            ],
        ];
    }

    private function approvedCoffeeForActiveSeason(): float
    {
        $query = DB::table('coffee_purchases')
            ->where('status', 'approved');

        if (Schema::hasTable('coffee_seasons')) {
            $activeSeasonId = DB::table('coffee_seasons')
                ->where('status', 'active')
                ->value('id');

            if ($activeSeasonId) {
                $query->where(
                    'coffee_season_id',
                    $activeSeasonId
                );
            }
        }

        return round(
            (float) $query->sum('quantity_kg'),
            2
        );
    }

    private function sumTodayByPossibleColumn(
        string $table,
        array $valueColumns,
        array $dateColumns
    ): float {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $valueColumn = collect($valueColumns)
            ->first(
                fn (string $column) =>
                    Schema::hasColumn($table, $column)
            );

        if (!$valueColumn) {
            return 0;
        }

        $query = DB::table($table);

        $dateColumn = collect($dateColumns)
            ->first(
                fn (string $column) =>
                    Schema::hasColumn($table, $column)
            );

        if ($dateColumn) {
            $query->whereDate(
                $dateColumn,
                today()->toDateString()
            );
        }

        if (Schema::hasColumn($table, 'status')) {
            $query->where('status', '!=', 'cancelled');
        }

        return round(
            (float) $query->sum($valueColumn),
            2
        );
    }

    private function todayCount(
        string $table
    ): int {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        if (Schema::hasColumn($table, 'created_at')) {
            $query->whereDate(
                'created_at',
                today()->toDateString()
            );
        }

        if (Schema::hasColumn($table, 'status')) {
            $query->where('status', '!=', 'cancelled');
        }

        return $query->count();
    }

    private function activeCount(
        string $table
    ): int {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        if (Schema::hasColumn($table, 'status')) {
            $statuses = collect(
                DB::table($table)
                    ->whereNotNull('status')
                    ->distinct()
                    ->pluck('status')
            );

            if ($statuses->contains('active')) {
                $query->where('status', 'active');
            } elseif ($statuses->contains('cancelled')) {
                $query->where('status', '!=', 'cancelled');
            }
        }

        return $query->count();
    }

    private function pendingCount(
        string $table
    ): int {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        if (!Schema::hasColumn($table, 'status')) {
            return DB::table($table)->count();
        }

        return DB::table($table)
            ->whereIn(
                'status',
                [
                    'pending',
                    'draft',
                    'open',
                ]
            )
            ->count();
    }

    private function averagePossibleColumn(
        string $table,
        array $columns
    ): float {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $column = collect($columns)
            ->first(
                fn (string $candidate) =>
                    Schema::hasColumn(
                        $table,
                        $candidate
                    )
            );

        if (!$column) {
            return 0;
        }

        return round(
            (float) (
                DB::table($table)
                    ->avg($column)
                ?? 0
            ),
            2
        );
    }

    private function changePercent(
        float $current,
        float $previous
    ): ?float {
        if ($previous == 0.0) {
            return $current == 0.0
                ? 0.0
                : null;
        }

        return round(
            (($current - $previous) / $previous) * 100,
            1
        );
    }

    private function activityTitle(
        ?string $action,
        ?string $module
    ): string {
        $actionText = $action
            ? str_replace('_', ' ', $action)
            : 'activity';

        $moduleText = $module
            ? str_replace('_', ' ', $module)
            : 'system';

        return ucfirst(
            "{$moduleText} {$actionText}"
        );
    }
}
