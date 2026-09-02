<?php

namespace App\Services\Reports;

use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\Payroll;
use App\Models\PettyCashTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportService
{
    public function overview(
        ?string $dateFrom,
        ?string $dateTo
    ): array {
        return [
            'period' => $this->period(
                $dateFrom,
                $dateTo
            ),

            'finance' => $this->finance(
                $dateFrom,
                $dateTo
            ),

            'payroll' => $this->payroll(
                $dateFrom,
                $dateTo
            ),

            'approvals' => $this->approvals(
                $dateFrom,
                $dateTo
            ),

            'operations' => $this->operations(
                $dateFrom,
                $dateTo
            ),

            'generated_at' => now()->toISOString(),
        ];
    }

    public function finance(
        ?string $dateFrom,
        ?string $dateTo
    ): array {
        $recordedExpenses = Expense::query()
            ->where(
                'status',
                Expense::STATUS_RECORDED
            );

        $this->applyDate(
            $recordedExpenses,
            'expense_date',
            $dateFrom,
            $dateTo
        );

        $cancelledExpenses = Expense::query()
            ->where(
                'status',
                Expense::STATUS_CANCELLED
            );

        $this->applyDate(
            $cancelledExpenses,
            'expense_date',
            $dateFrom,
            $dateTo
        );

        $pettyFunding = PettyCashTransaction::query()
            ->where(
                'transaction_type',
                'fund_in'
            )
            ->where(
                'status',
                'posted'
            );

        $this->applyDate(
            $pettyFunding,
            'transaction_date',
            $dateFrom,
            $dateTo
        );

        $pettyExpenses = PettyCashTransaction::query()
            ->where(
                'transaction_type',
                'expense'
            )
            ->where(
                'status',
                'posted'
            );

        $this->applyDate(
            $pettyExpenses,
            'transaction_date',
            $dateFrom,
            $dateTo
        );

        $latestPettyCash =
            PettyCashTransaction::query()
                ->orderByDesc('id')
                ->first();

        return [
            'period' => $this->period(
                $dateFrom,
                $dateTo
            ),

            'expenses' => [
                'recorded_count' =>
                    (clone $recordedExpenses)->count(),

                'recorded_amount' =>
                    $this->money(
                        (clone $recordedExpenses)
                            ->sum('amount')
                    ),

                'cancelled_count' =>
                    (clone $cancelledExpenses)->count(),

                'cancelled_amount' =>
                    $this->money(
                        (clone $cancelledExpenses)
                            ->sum('amount')
                    ),
            ],

            'petty_cash' => [
                'funding_count' =>
                    (clone $pettyFunding)->count(),

                'funding_amount' =>
                    $this->money(
                        (clone $pettyFunding)
                            ->sum('amount')
                    ),

                'expense_count' =>
                    (clone $pettyExpenses)->count(),

                'expense_amount' =>
                    $this->money(
                        (clone $pettyExpenses)
                            ->sum('amount')
                    ),

                'current_balance' =>
                    $this->money(
                        $latestPettyCash
                            ?->balance_after
                    ),
            ],

            'total_operational_spending' =>
                $this->money(
                    (float) (
                        (clone $recordedExpenses)
                            ->sum('amount')
                    )
                ),

            'currency' => 'RWF',
        ];
    }

    public function payroll(
        ?string $dateFrom,
        ?string $dateTo
    ): array {
        $base = Payroll::query();

        $this->applyPayrollPeriod(
            $base,
            $dateFrom,
            $dateTo
        );

        $draft = clone $base;
        $processed = clone $base;
        $paid = clone $base;
        $cancelled = clone $base;

        $draft->where(
            'status',
            Payroll::STATUS_DRAFT
        );

        $processed->where(
            'status',
            Payroll::STATUS_PROCESSED
        );

        $paid->where(
            'status',
            Payroll::STATUS_PAID
        );

        $cancelled->where(
            'status',
            Payroll::STATUS_CANCELLED
        );

        return [
            'period' => $this->period(
                $dateFrom,
                $dateTo
            ),

            'total_records' =>
                (clone $base)->count(),

            'draft_count' =>
                $draft->count(),

            'processed_count' =>
                $processed->count(),

            'paid_count' =>
                $paid->count(),

            'cancelled_count' =>
                $cancelled->count(),

            'gross_salary' =>
                $this->money(
                    (clone $base)
                        ->whereNot(
                            'status',
                            Payroll::STATUS_CANCELLED
                        )
                        ->sum('gross_salary')
                ),

            'deductions' =>
                $this->money(
                    (clone $base)
                        ->whereNot(
                            'status',
                            Payroll::STATUS_CANCELLED
                        )
                        ->sum('deductions')
                ),

            'net_salary' =>
                $this->money(
                    (clone $base)
                        ->whereNot(
                            'status',
                            Payroll::STATUS_CANCELLED
                        )
                        ->sum('net_salary')
                ),

            'paid_amount' =>
                $this->money(
                    (clone $base)
                        ->where(
                            'status',
                            Payroll::STATUS_PAID
                        )
                        ->sum('net_salary')
                ),

            'outstanding_amount' =>
                $this->money(
                    (clone $base)
                        ->where(
                            'status',
                            Payroll::STATUS_PROCESSED
                        )
                        ->sum('net_salary')
                ),

            'currency' => 'RWF',
        ];
    }

    public function approvals(
        ?string $dateFrom,
        ?string $dateTo
    ): array {
        $base = ApprovalRequest::query();

        $this->applyDate(
            $base,
            'requested_at',
            $dateFrom,
            $dateTo
        );

        return [
            'period' => $this->period(
                $dateFrom,
                $dateTo
            ),

            'total' =>
                (clone $base)->count(),

            'pending' =>
                (clone $base)
                    ->where(
                        'status',
                        ApprovalRequest::STATUS_PENDING
                    )
                    ->count(),

            'approved' =>
                (clone $base)
                    ->where(
                        'status',
                        ApprovalRequest::STATUS_APPROVED
                    )
                    ->count(),

            'rejected' =>
                (clone $base)
                    ->where(
                        'status',
                        ApprovalRequest::STATUS_REJECTED
                    )
                    ->count(),

            'cancelled' =>
                (clone $base)
                    ->where(
                        'status',
                        ApprovalRequest::STATUS_CANCELLED
                    )
                    ->count(),

            'pending_amount' =>
                $this->money(
                    (clone $base)
                        ->where(
                            'status',
                            ApprovalRequest::STATUS_PENDING
                        )
                        ->sum('amount')
                ),

            'approved_amount' =>
                $this->money(
                    (clone $base)
                        ->where(
                            'status',
                            ApprovalRequest::STATUS_APPROVED
                        )
                        ->sum('amount')
                ),

            'applied_amount' =>
                $this->money(
                    (clone $base)
                        ->where(
                            'status',
                            ApprovalRequest::STATUS_APPROVED
                        )
                        ->whereNotNull('applied_at')
                        ->sum('amount')
                ),

            'currency' => 'RWF',
        ];
    }

    public function operations(
        ?string $dateFrom,
        ?string $dateTo
    ): array {
        $tables = [
            'coffee_purchases' =>
                'coffee_purchases',

            'direct_farmer_deliveries' =>
                'direct_farmer_deliveries',

            'agent_collections' =>
                'agent_collections',

            'field_weighings' =>
                'field_weighings',

            'collection_trips' =>
                'collection_trips',

            'factory_receptions' =>
                'factory_receptions',

            'coffee_lots' =>
                'coffee_lots',

            'store_inventories' =>
                'store_inventories',

            'stock_movements' =>
                'stock_movements',

            'processing_batches' =>
                'processing_batches',
        ];

        $results = [];

        foreach ($tables as $key => $table) {
            $results[$key] =
                $this->tableCount(
                    $table,
                    $dateFrom,
                    $dateTo
                );
        }

        return [
            'period' => $this->period(
                $dateFrom,
                $dateTo
            ),

            ...$results,
        ];
    }

    private function tableCount(
        string $table,
        ?string $dateFrom,
        ?string $dateTo
    ): int {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        if (
            Schema::hasColumn(
                $table,
                'created_at'
            )
        ) {
            if ($dateFrom) {
                $query->whereDate(
                    'created_at',
                    '>=',
                    $dateFrom
                );
            }

            if ($dateTo) {
                $query->whereDate(
                    'created_at',
                    '<=',
                    $dateTo
                );
            }
        }

        return $query->count();
    }

    private function applyDate(
        Builder $query,
        string $column,
        ?string $dateFrom,
        ?string $dateTo
    ): void {
        if ($dateFrom) {
            $query->whereDate(
                $column,
                '>=',
                $dateFrom
            );
        }

        if ($dateTo) {
            $query->whereDate(
                $column,
                '<=',
                $dateTo
            );
        }
    }

    private function applyPayrollPeriod(
        Builder $query,
        ?string $dateFrom,
        ?string $dateTo
    ): void {
        if ($dateFrom) {
            $query->where(
                'payroll_month',
                '>=',
                substr(
                    $dateFrom,
                    0,
                    7
                )
            );
        }

        if ($dateTo) {
            $query->where(
                'payroll_month',
                '<=',
                substr(
                    $dateTo,
                    0,
                    7
                )
            );
        }
    }

    private function period(
        ?string $dateFrom,
        ?string $dateTo
    ): array {
        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    private function money(
        mixed $value
    ): string {
        return number_format(
            (float) ($value ?? 0),
            2,
            '.',
            ''
        );
    }
}
