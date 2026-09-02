<?php

namespace Tests\Feature\API\Notification;

use App\Models\Payroll;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_own_notifications(): void
    {
        $adminOne = $this->user('admin');
        $adminTwo = $this->user('admin');

        $service = app(
            SystemNotificationService::class
        );

        $service->sendToUser(
            $adminOne,
            [
                'type' => 'test',
                'title' => 'Admin One',
                'message' => 'Private message',
            ]
        );

        $service->sendToUser(
            $adminTwo,
            [
                'type' => 'test',
                'title' => 'Admin Two',
                'message' => 'Private message',
            ]
        );

        Sanctum::actingAs($adminOne);

        $response = $this->getJson(
            '/api/notifications'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.items.0.title',
                'Admin One'
            );
    }

    public function test_summary_returns_unread_count(): void
    {
        $user = $this->user('accountant');

        $service = app(
            SystemNotificationService::class
        );

        $service->sendToUser(
            $user,
            [
                'type' => 'test',
                'title' => 'Unread',
                'message' => 'Unread notification',
            ]
        );

        $notification = $service->sendToUser(
            $user,
            [
                'type' => 'test',
                'title' => 'Read',
                'message' => 'Read notification',
            ]
        );

        $notification->update([
            'read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/notifications/summary'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.total',
                2
            )
            ->assertJsonPath(
                'data.unread',
                1
            )
            ->assertJsonPath(
                'data.read',
                1
            );
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = $this->user('accountant');

        $notification =
            SystemNotification::create([
                'recipient_id' =>
                    $user->id,

                'type' =>
                    'test',

                'title' =>
                    'Test',

                'message' =>
                    'Notification',
            ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            "/api/notifications/{$notification->id}/read"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.is_read',
                true
            );

        $this->assertNotNull(
            $notification
                ->fresh()
                ->read_at
        );
    }

    public function test_user_can_mark_notification_as_unread(): void
    {
        $user = $this->user('accountant');

        $notification =
            SystemNotification::create([
                'recipient_id' =>
                    $user->id,

                'type' =>
                    'test',

                'title' =>
                    'Test',

                'message' =>
                    'Notification',

                'read_at' =>
                    now(),
            ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            "/api/notifications/{$notification->id}/unread"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.is_read',
                false
            );

        $this->assertNull(
            $notification
                ->fresh()
                ->read_at
        );
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $owner = $this->user('admin');
        $other = $this->user('accountant');

        $notification =
            SystemNotification::create([
                'recipient_id' =>
                    $owner->id,

                'type' =>
                    'test',

                'title' =>
                    'Private',

                'message' =>
                    'Private notification',
            ]);

        Sanctum::actingAs($other);

        $this->patchJson(
            "/api/notifications/{$notification->id}/read"
        )->assertForbidden();
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = $this->user('accountant');

        SystemNotification::create([
            'recipient_id' => $user->id,
            'type' => 'test',
            'title' => 'One',
            'message' => 'One',
        ]);

        SystemNotification::create([
            'recipient_id' => $user->id,
            'type' => 'test',
            'title' => 'Two',
            'message' => 'Two',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            '/api/notifications/read-all'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.updated',
                2
            );

        $this->assertSame(
            0,
            SystemNotification::query()
                ->where(
                    'recipient_id',
                    $user->id
                )
                ->whereNull('read_at')
                ->count()
        );
    }

    public function test_active_admins_receive_notification_when_accountant_requests_approval(): void
    {
        $adminOne = $this->user('admin');
        $adminTwo = $this->user('admin');

        $inactiveAdmin = $this->user(
            'admin',
            false
        );

        $accountant = $this->user(
            'accountant'
        );

        $employee = $this->user(
            'driver'
        );

        $payroll = $this->processedPayroll(
            $employee,
            $accountant
        );

        Sanctum::actingAs($accountant);

        $this->postJson(
            "/api/approvals/payrolls/{$payroll->id}/payment"
        )->assertCreated();

        $this->assertDatabaseHas(
            'system_notifications',
            [
                'recipient_id' =>
                    $adminOne->id,

                'type' =>
                    SystemNotification::TYPE_APPROVAL_REQUESTED,
            ]
        );

        $this->assertDatabaseHas(
            'system_notifications',
            [
                'recipient_id' =>
                    $adminTwo->id,

                'type' =>
                    SystemNotification::TYPE_APPROVAL_REQUESTED,
            ]
        );

        $this->assertDatabaseMissing(
            'system_notifications',
            [
                'recipient_id' =>
                    $inactiveAdmin->id,
            ]
        );
    }

    public function test_requester_receives_notification_when_admin_approves(): void
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
            "/api/approvals/{$approvalId}/approve"
        )->assertOk();

        $this->assertDatabaseHas(
            'system_notifications',
            [
                'recipient_id' =>
                    $accountant->id,

                'type' =>
                    SystemNotification::TYPE_APPROVAL_APPROVED,

                'reference_id' =>
                    $approvalId,
            ]
        );
    }

    public function test_requester_receives_notification_when_admin_rejects(): void
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
                    'Please correct the Payroll.',
            ]
        )->assertOk();

        $this->assertDatabaseHas(
            'system_notifications',
            [
                'recipient_id' =>
                    $accountant->id,

                'type' =>
                    SystemNotification::TYPE_APPROVAL_REJECTED,

                'reference_id' =>
                    $approvalId,
            ]
        );
    }

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $this->getJson(
            '/api/notifications'
        )->assertUnauthorized();
    }

    private function processedPayroll(
        User $employee,
        User $creator
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
                Payroll::STATUS_PROCESSED,

            'created_by' =>
                $creator->id,

            'processed_by' =>
                $creator->id,

            'processed_at' =>
                now(),
        ]);

        return $payroll->fresh();
    }

    private function user(
        string $role,
        bool $active = true
    ): User {
        return User::factory()->create([
            'role' => $role,
            'is_active' => $active,
            'must_change_password' => false,
        ]);
    }
}
