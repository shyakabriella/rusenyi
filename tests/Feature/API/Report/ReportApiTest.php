<?php

namespace Tests\Feature\API\Report;

use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_report_overview(): void
    {
        $admin = $this->user('admin');

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/reports/overview'
        )
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'finance',
                    'payroll',
                    'approvals',
                    'operations',
                    'generated_at',
                ],
            ]);
    }

    public function test_accountant_can_view_reports(): void
    {
        $accountant =
            $this->user('accountant');

        Sanctum::actingAs(
            $accountant
        );

        $this->getJson(
            '/api/reports/finance'
        )->assertOk();

        $this->getJson(
            '/api/reports/payroll'
        )->assertOk();

        $this->getJson(
            '/api/reports/approvals'
        )->assertOk();

        $this->getJson(
            '/api/reports/operations'
        )->assertOk();
    }

    public function test_agent_cannot_view_reports(): void
    {
        $agent = $this->user('agent');

        Sanctum::actingAs($agent);

        $this->getJson(
            '/api/reports/overview'
        )->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_view_reports(): void
    {
        $this->getJson(
            '/api/reports/overview'
        )->assertUnauthorized();
    }

    public function test_finance_report_returns_recorded_expenses(): void
    {
        $admin = $this->user('admin');

        $this->expense(
            $admin,
            100000,
            Expense::STATUS_RECORDED
        );

        $this->expense(
            $admin,
            50000,
            Expense::STATUS_RECORDED
        );

        $this->expense(
            $admin,
            25000,
            Expense::STATUS_CANCELLED
        );

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/reports/finance'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.expenses.recorded_count',
                2
            )
            ->assertJsonPath(
                'data.expenses.recorded_amount',
                '150000.00'
            )
            ->assertJsonPath(
                'data.expenses.cancelled_count',
                1
            )
            ->assertJsonPath(
                'data.expenses.cancelled_amount',
                '25000.00'
            );
    }

    public function test_payroll_report_returns_salary_totals(): void
    {
        $admin = $this->user('admin');

        $employeeOne =
            $this->user('driver');

        $employeeTwo =
            $this->user('store');

        $this->payroll(
            $employeeOne,
            $admin,
            Payroll::STATUS_PAID,
            250000
        );

        $this->payroll(
            $employeeTwo,
            $admin,
            Payroll::STATUS_PROCESSED,
            300000
        );

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/reports/payroll'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.total_records',
                2
            )
            ->assertJsonPath(
                'data.paid_count',
                1
            )
            ->assertJsonPath(
                'data.processed_count',
                1
            )
            ->assertJsonPath(
                'data.paid_amount',
                '250000.00'
            )
            ->assertJsonPath(
                'data.outstanding_amount',
                '300000.00'
            );
    }

    public function test_approval_report_returns_status_counts(): void
    {
        $admin =
            $this->user('admin');

        $accountant =
            $this->user('accountant');

        $this->approval(
            $accountant,
            ApprovalRequest::STATUS_PENDING,
            200000
        );

        $this->approval(
            $accountant,
            ApprovalRequest::STATUS_APPROVED,
            300000
        );

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/reports/approvals'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.total',
                2
            )
            ->assertJsonPath(
                'data.pending',
                1
            )
            ->assertJsonPath(
                'data.approved',
                1
            )
            ->assertJsonPath(
                'data.pending_amount',
                '200000.00'
            )
            ->assertJsonPath(
                'data.approved_amount',
                '300000.00'
            );
    }

    public function test_report_can_be_filtered_by_date(): void
    {
        $admin = $this->user('admin');

        $old = $this->expense(
            $admin,
            50000,
            Expense::STATUS_RECORDED
        );

        $old->update([
            'expense_date' =>
                '2026-01-10',
        ]);

        $current = $this->expense(
            $admin,
            90000,
            Expense::STATUS_RECORDED
        );

        $current->update([
            'expense_date' =>
                '2026-09-02',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/reports/finance?' .
            'date_from=2026-09-01&' .
            'date_to=2026-09-30'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.expenses.recorded_count',
                1
            )
            ->assertJsonPath(
                'data.expenses.recorded_amount',
                '90000.00'
            );
    }

    public function test_invalid_report_date_range_is_rejected(): void
    {
        $admin = $this->user('admin');

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/reports/finance?' .
            'date_from=2026-09-20&' .
            'date_to=2026-09-01'
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'date_to'
            );
    }

    private function expense(
        User $creator,
        float $amount,
        string $status
    ): Expense {
        return Expense::create([
            'expense_date' =>
                now()->toDateString(),

            'category' =>
                'operations',

            'payee_name' =>
                'Test Supplier',

            'description' =>
                'Report test expense',

            'amount' =>
                $amount,

            'currency' =>
                'RWF',

            'payment_method' =>
                Expense::PAYMENT_CASH,

            'status' =>
                $status,

            'created_by' =>
                $creator->id,

            'recorded_by' =>
                $status ===
                    Expense::STATUS_RECORDED
                    ? $creator->id
                    : null,

            'recorded_at' =>
                $status ===
                    Expense::STATUS_RECORDED
                    ? now()
                    : null,
        ]);
    }

    private function payroll(
        User $employee,
        User $creator,
        string $status,
        float $netSalary
    ): Payroll {
        return Payroll::create([
            'employee_id' =>
                $employee->id,

            'employee_name' =>
                $employee->name,

            'employee_role' =>
                $employee->role,

            'payroll_month' =>
                '2026-09',

            'basic_salary' =>
                $netSalary,

            'allowances' =>
                0,

            'gross_salary' =>
                $netSalary,

            'deductions' =>
                0,

            'net_salary' =>
                $netSalary,

            'currency' =>
                'RWF',

            'status' =>
                $status,

            'created_by' =>
                $creator->id,

            'processed_by' =>
                $creator->id,

            'processed_at' =>
                now(),

            'paid_by' =>
                $status ===
                    Payroll::STATUS_PAID
                    ? $creator->id
                    : null,

            'paid_at' =>
                $status ===
                    Payroll::STATUS_PAID
                    ? now()
                    : null,

            'payment_date' =>
                $status ===
                    Payroll::STATUS_PAID
                    ? now()->toDateString()
                    : null,
        ]);
    }

    private function approval(
        User $requester,
        string $status,
        float $amount
    ): ApprovalRequest {
        return ApprovalRequest::create([
            'module' =>
                ApprovalRequest::MODULE_PAYROLL,

            'action' =>
                ApprovalRequest::ACTION_PAYMENT,

            'reference_type' =>
                'payroll',

            'reference_id' =>
                999,

            'reference_code' =>
                'PAY-TEST',

            'title' =>
                'Report test approval',

            'description' =>
                'Report test',

            'amount' =>
                $amount,

            'currency' =>
                'RWF',

            'status' =>
                $status,

            'requested_by' =>
                $requester->id,

            'requested_at' =>
                now(),
        ]);
    }

    private function user(
        string $role
    ): User {
        return User::factory()->create([
            'role' =>
                $role,

            'is_active' =>
                true,

            'must_change_password' =>
                false,
        ]);
    }
}
