<?php

namespace Tests\Feature\API\Approval;

use App\Models\ApprovalRequest;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_request_payment_approval_for_processed_payroll(): void
    {
        $accountant = $this->user('accountant');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $accountant
        );

        Sanctum::actingAs($accountant);

        $response = $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment",
            [
                'request_note' =>
                    'Please approve this salary payment.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.module',
                ApprovalRequest::MODULE_PAYROLL
            )
            ->assertJsonPath(
                'data.action',
                ApprovalRequest::ACTION_PAYMENT
            )
            ->assertJsonPath(
                'data.reference_id',
                $payroll->id
            )
            ->assertJsonPath(
                'data.status',
                ApprovalRequest::STATUS_PENDING
            );

        $this->assertDatabaseHas(
            'approval_requests',
            [
                'module' =>
                    ApprovalRequest::MODULE_PAYROLL,

                'action' =>
                    ApprovalRequest::ACTION_PAYMENT,

                'reference_type' =>
                    'payroll',

                'reference_id' =>
                    $payroll->id,

                'requested_by' =>
                    $accountant->id,

                'status' =>
                    ApprovalRequest::STATUS_PENDING,
            ]
        );
    }

    public function test_admin_cannot_create_payroll_payment_approval_request(): void
    {
        $admin = $this->user('admin');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $admin
        );

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )->assertForbidden();
    }

    public function test_draft_payroll_cannot_be_submitted_for_payment_approval(): void
    {
        $accountant = $this->user('accountant');
        $employee = $this->user('driver');

        $payroll = $this->payroll(
            $employee,
            $accountant,
            Payroll::STATUS_DRAFT
        );

        Sanctum::actingAs($accountant);

        $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'success',
                false
            );
    }

    public function test_payroll_cannot_have_two_active_payment_approvals(): void
    {
        $accountant = $this->user('accountant');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $accountant
        );

        Sanctum::actingAs($accountant);

        $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )->assertCreated();

        $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )->assertStatus(422);

        $this->assertSame(
            1,
            ApprovalRequest::query()
                ->where(
                    'reference_type',
                    'payroll'
                )
                ->where(
                    'reference_id',
                    $payroll->id
                )
                ->count()
        );
    }

    public function test_admin_can_approve_pending_request(): void
    {
        $admin = $this->user('admin');
        $accountant = $this->user('accountant');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $accountant
        );

        Sanctum::actingAs($accountant);

        $approvalId = $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/approvals/{$approvalId}/approve",
            [
                'review_note' =>
                    'Salary payment approved.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                ApprovalRequest::STATUS_APPROVED
            )
            ->assertJsonPath(
                'data.reviewer.id',
                $admin->id
            );

        $this->assertDatabaseHas(
            'approval_requests',
            [
                'id' => $approvalId,

                'status' =>
                    ApprovalRequest::STATUS_APPROVED,

                'reviewed_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_accountant_cannot_approve_request(): void
    {
        $admin = $this->user('admin');
        $accountant = $this->user('accountant');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $accountant
        );

        Sanctum::actingAs($accountant);

        $approvalId = $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )
            ->assertCreated()
            ->json('data.id');

        $this->patchJson(
            "/api/approvals/{$approvalId}/approve"
        )->assertForbidden();

        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/approvals/{$approvalId}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                ApprovalRequest::STATUS_PENDING
            );
    }

    public function test_requester_cannot_approve_own_request(): void
    {
        $admin = $this->user('admin');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $admin
        );

        $approval = ApprovalRequest::create([
            'module' =>
                ApprovalRequest::MODULE_PAYROLL,

            'action' =>
                ApprovalRequest::ACTION_PAYMENT,

            'reference_type' =>
                'payroll',

            'reference_id' =>
                $payroll->id,

            'reference_code' =>
                $payroll->payroll_code,

            'title' =>
                'Payroll payment approval',

            'description' =>
                'Test approval',

            'amount' =>
                $payroll->net_salary,

            'currency' =>
                'RWF',

            'status' =>
                ApprovalRequest::STATUS_PENDING,

            'requested_by' =>
                $admin->id,

            'requested_at' =>
                now(),
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/approvals/{$approval->id}/approve"
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'success',
                false
            );

        $this->assertDatabaseHas(
            'approval_requests',
            [
                'id' =>
                    $approval->id,

                'status' =>
                    ApprovalRequest::STATUS_PENDING,
            ]
        );
    }

    public function test_admin_can_reject_pending_request(): void
    {
        $admin = $this->user('admin');
        $accountant = $this->user('accountant');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $accountant
        );

        Sanctum::actingAs($accountant);

        $approvalId = $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/approvals/{$approvalId}/reject",
            [
                'review_note' =>
                    'Salary information needs correction.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                ApprovalRequest::STATUS_REJECTED
            );

        $this->assertDatabaseHas(
            'approval_requests',
            [
                'id' =>
                    $approvalId,

                'status' =>
                    ApprovalRequest::STATUS_REJECTED,

                'reviewed_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_rejection_reason_is_required(): void
    {
        $admin = $this->user('admin');
        $accountant = $this->user('accountant');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $accountant
        );

        Sanctum::actingAs($accountant);

        $approvalId = $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/approvals/{$approvalId}/reject",
            []
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'review_note'
            );
    }

    public function test_rejected_request_allows_new_payment_approval_request(): void
    {
        $admin = $this->user('admin');
        $accountant = $this->user('accountant');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $accountant
        );

        Sanctum::actingAs($accountant);

        $firstApprovalId = $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/approvals/{$firstApprovalId}/reject",
            [
                'review_note' =>
                    'Correct and submit again.',
            ]
        )->assertOk();

        Sanctum::actingAs($accountant);

        $secondApprovalId = $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment",
            [
                'request_note' =>
                    'Corrected request.',
            ]
        )
            ->assertCreated()
            ->json('data.id');

        $this->assertNotSame(
            $firstApprovalId,
            $secondApprovalId
        );

        $this->assertSame(
            2,
            ApprovalRequest::query()
                ->where(
                    'reference_id',
                    $payroll->id
                )
                ->count()
        );
    }

    public function test_requester_can_cancel_pending_request(): void
    {
        $accountant = $this->user('accountant');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $accountant
        );

        Sanctum::actingAs($accountant);

        $approvalId = $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )
            ->assertCreated()
            ->json('data.id');

        $this->patchJson(
            "/api/approvals/{$approvalId}/cancel",
            [
                'cancellation_reason' =>
                    'Payroll needs correction.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                ApprovalRequest::STATUS_CANCELLED
            );
    }

    public function test_accountant_cannot_cancel_another_accountants_request(): void
    {
        $accountantOne =
            $this->user('accountant');

        $accountantTwo =
            $this->user('accountant');

        $employee =
            $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $accountantOne
        );

        Sanctum::actingAs(
            $accountantOne
        );

        $approvalId = $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs(
            $accountantTwo
        );

        $this->patchJson(
            "/api/approvals/{$approvalId}/cancel",
            [
                'cancellation_reason' =>
                    'Trying to cancel.',
            ]
        )->assertForbidden();
    }

    public function test_payroll_cannot_be_paid_without_approved_request(): void
    {
        $accountant = $this->user('accountant');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $accountant
        );

        Sanctum::actingAs($accountant);

        $this->patchJson(
            "/api/payrolls/{$payroll->id}/pay",
            [
                'payment_method' =>
                    Payroll::PAYMENT_CASH,

                'payment_date' =>
                    now()->toDateString(),
            ]
        )
            ->assertStatus(422);

        $this->assertDatabaseHas(
            'payrolls',
            [
                'id' =>
                    $payroll->id,

                'status' =>
                    Payroll::STATUS_PROCESSED,
            ]
        );
    }

    public function test_approved_payroll_can_be_paid_and_approval_is_applied(): void
    {
        $admin = $this->user('admin');
        $accountant = $this->user('accountant');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $accountant
        );

        Sanctum::actingAs($accountant);

        $approvalId = $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment",
            [
                'request_note' =>
                    'Please approve salary.',
            ]
        )
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/approvals/{$approvalId}/approve",
            [
                'review_note' =>
                    'Approved for payment.',
            ]
        )->assertOk();

        Sanctum::actingAs($accountant);

        $this->patchJson(
            "/api/payrolls/{$payroll->id}/pay",
            [
                'payment_method' =>
                    Payroll::PAYMENT_BANK_TRANSFER,

                'payment_reference' =>
                    'BK-PAY-001',

                'payment_date' =>
                    now()->toDateString(),
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                Payroll::STATUS_PAID
            )
            ->assertJsonPath(
                'data.payment_approval_id',
                $approvalId
            );

        $payroll->refresh();

        $approval =
            ApprovalRequest::findOrFail(
                $approvalId
            );

        $this->assertSame(
            Payroll::STATUS_PAID,
            $payroll->status
        );

        $this->assertSame(
            $approvalId,
            $payroll->payment_approval_id
        );

        $this->assertNotNull(
            $approval->applied_at
        );

        $this->assertSame(
            $accountant->id,
            $approval->applied_by
        );
    }

    public function test_cancelling_payroll_cancels_pending_payment_approval(): void
    {
        $accountant = $this->user('accountant');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $accountant
        );

        Sanctum::actingAs($accountant);

        $approvalId = $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )
            ->assertCreated()
            ->json('data.id');

        $this->patchJson(
            "/api/payrolls/{$payroll->id}/cancel",
            [
                'cancellation_reason' =>
                    'Salary details are incorrect.',
            ]
        )->assertOk();

        $this->assertDatabaseHas(
            'payrolls',
            [
                'id' =>
                    $payroll->id,

                'status' =>
                    Payroll::STATUS_CANCELLED,
            ]
        );

        $this->assertDatabaseHas(
            'approval_requests',
            [
                'id' =>
                    $approvalId,

                'status' =>
                    ApprovalRequest::STATUS_CANCELLED,
            ]
        );
    }

    public function test_admin_and_accountant_can_view_approvals(): void
    {
        $admin = $this->user('admin');
        $accountant = $this->user('accountant');
        $employee = $this->user('driver');

        $payroll = $this->processedPayroll(
            $employee,
            $accountant
        );

        Sanctum::actingAs($accountant);

        $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )->assertCreated();

        $this->getJson(
            '/api/approvals'
        )->assertOk();

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/approvals'
        )->assertOk();

        $this->getJson(
            '/api/approvals/summary'
        )->assertOk()
            ->assertJsonPath(
                'data.pending_requests',
                1
            );
    }

    public function test_other_roles_cannot_access_approvals(): void
    {
        $agent = $this->user('agent');

        Sanctum::actingAs($agent);

        $this->getJson(
            '/api/approvals'
        )->assertForbidden();

        $this->getJson(
            '/api/approvals/summary'
        )->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_approvals(): void
    {
        $this->getJson(
            '/api/approvals'
        )->assertUnauthorized();
    }

    private function processedPayroll(
        User $employee,
        User $creator
    ): Payroll {
        return $this->payroll(
            $employee,
            $creator,
            Payroll::STATUS_PROCESSED
        );
    }

    private function payroll(
        User $employee,
        User $creator,
        string $status
    ): Payroll {
        $payroll = Payroll::create([
            'employee_id' =>
                $employee->id,

            'employee_name' =>
                $employee->name,

            'employee_role' =>
                $employee->role,

            'payroll_month' =>
                now()->format('Y-m'),

            'basic_salary' =>
                250000,

            'allowances' =>
                40000,

            'gross_salary' =>
                290000,

            'deductions' =>
                15000,

            'net_salary' =>
                275000,

            'currency' =>
                'RWF',

            'status' =>
                $status,

            'created_by' =>
                $creator->id,
        ]);

        if (
            $status ===
            Payroll::STATUS_PROCESSED
        ) {
            $payroll->update([
                'processed_by' =>
                    $creator->id,

                'processed_at' =>
                    now(),
            ]);
        }

        return $payroll->fresh();
    }

    private function user(
        string $role
    ): User {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }
}
