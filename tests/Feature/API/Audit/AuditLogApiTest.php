<?php

namespace Tests\Feature\API\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_view_audit_logs(): void
    {
        $this->getJson('/api/audit-logs')
            ->assertUnauthorized();
    }

    public function test_admin_can_view_audit_logs(): void
    {
        $admin = $this->admin();

        AuditLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => $admin->role,
            'action' => AuditLog::ACTION_CREATED,
            'module' => 'payroll',
            'auditable_type' => 'App\\Models\\Payroll',
            'auditable_id' => 10,
            'auditable_code' => 'PAY-000010',
            'description' => 'Created Payroll',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.module', 'payroll')
            ->assertJsonPath('data.items.0.action', 'created');
    }

    public function test_accountant_cannot_view_audit_logs(): void
    {
        $accountant = $this->user('accountant');

        Sanctum::actingAs($accountant);

        $this->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    public function test_admin_can_view_audit_summary(): void
    {
        $admin = $this->admin();

        AuditLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => $admin->role,
            'action' => AuditLog::ACTION_CREATED,
            'module' => 'expense',
            'auditable_type' => 'App\\Models\\Expense',
            'auditable_id' => 1,
            'description' => 'Created Expense',
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => $admin->role,
            'action' => AuditLog::ACTION_UPDATED,
            'module' => 'expense',
            'auditable_type' => 'App\\Models\\Expense',
            'auditable_id' => 1,
            'description' => 'Updated Expense',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/audit-logs/summary')
            ->assertOk()
            ->assertJsonPath('data.total_logs', 2)
            ->assertJsonPath('data.created_actions', 1)
            ->assertJsonPath('data.updated_actions', 1);
    }

    public function test_admin_can_view_one_audit_log(): void
    {
        $admin = $this->admin();

        $audit = AuditLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => $admin->role,
            'action' => AuditLog::ACTION_UPDATED,
            'module' => 'approval_request',
            'auditable_type' => 'App\\Models\\ApprovalRequest',
            'auditable_id' => 5,
            'auditable_code' => 'APP-000005',
            'description' => 'Updated Approval Request',
            'old_values' => [
                'status' => 'pending',
            ],
            'new_values' => [
                'status' => 'approved',
            ],
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/audit-logs/{$audit->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $audit->id)
            ->assertJsonPath('data.module', 'approval_request')
            ->assertJsonPath('data.old_values.status', 'pending')
            ->assertJsonPath('data.new_values.status', 'approved');
    }

    public function test_admin_can_filter_by_module(): void
    {
        $admin = $this->admin();

        $this->audit($admin, 'payroll', 'created', 1);
        $this->audit($admin, 'expense', 'created', 2);

        Sanctum::actingAs($admin);

        $this->getJson('/api/audit-logs?module=payroll')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.module', 'payroll');
    }

    public function test_admin_can_filter_by_action(): void
    {
        $admin = $this->admin();

        $this->audit($admin, 'payroll', 'created', 1);
        $this->audit($admin, 'payroll', 'updated', 2);

        Sanctum::actingAs($admin);

        $this->getJson('/api/audit-logs?action=updated')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.action', 'updated');
    }

    public function test_admin_can_search_audit_logs(): void
    {
        $admin = $this->admin();

        AuditLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => $admin->role,
            'action' => 'updated',
            'module' => 'payroll',
            'auditable_type' => 'App\\Models\\Payroll',
            'auditable_id' => 8,
            'auditable_code' => 'PAY-000008',
            'description' => 'Updated monthly payroll',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/audit-logs?search=PAY-000008')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.auditable_code', 'PAY-000008');
    }

    public function test_audit_code_is_generated_automatically(): void
    {
        $admin = $this->admin();

        $audit = AuditLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => $admin->role,
            'action' => 'created',
            'module' => 'expense',
            'auditable_type' => 'App\\Models\\Expense',
            'auditable_id' => 1,
            'description' => 'Created Expense',
        ]);

        $audit->refresh();

        $this->assertNotNull($audit->audit_code);
        $this->assertSame(
            sprintf('AUD-%06d', $audit->id),
            $audit->audit_code
        );
    }

    private function audit(
        User $user,
        string $module,
        string $action,
        int $id
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => $module,
            'auditable_type' => 'App\\Models\\TestModel',
            'auditable_id' => $id,
            'description' => ucfirst($action) . ' ' . $module,
        ]);
    }

    private function admin(): User
    {
        return $this->user('admin');
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }
}
