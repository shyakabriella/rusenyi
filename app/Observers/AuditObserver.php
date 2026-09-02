<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditObserver
{
    public function created(Model $model): void
    {
        if (!$this->shouldAudit($model)) {
            return;
        }

        $this->record(
            $model,
            AuditLog::ACTION_CREATED,
            null,
            $this->clean(
                $model->getAttributes()
            )
        );
    }

    public function updated(Model $model): void
    {
        if (!$this->shouldAudit($model)) {
            return;
        }

        $changes = $model->getChanges();

        unset(
            $changes['updated_at']
        );

        if (!$changes) {
            return;
        }

        $oldValues = [];

        foreach (
            array_keys($changes)
            as $key
        ) {
            $oldValues[$key] =
                $model->getRawOriginal(
                    $key
                );
        }

        $this->record(
            $model,
            AuditLog::ACTION_UPDATED,
            $this->clean(
                $oldValues
            ),
            $this->clean(
                $changes
            )
        );
    }

    public function deleted(Model $model): void
    {
        if (!$this->shouldAudit($model)) {
            return;
        }

        $this->record(
            $model,
            AuditLog::ACTION_DELETED,
            $this->clean(
                $model->getAttributes()
            ),
            null
        );
    }

    private function shouldAudit(
        Model $model
    ): bool {
        if (
            $model instanceof AuditLog
        ) {
            return false;
        }

        return Auth::check();
    }

    private function record(
        Model $model,
        string $action,
        ?array $oldValues,
        ?array $newValues
    ): void {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        $request = request();

        $code =
            $this->recordCode(
                $model
            );

        AuditLog::create([
            'user_id' =>
                $user->id,

            'user_name' =>
                $user->name,

            'user_role' =>
                $user->role,

            'action' =>
                $action,

            'module' =>
                Str::snake(
                    class_basename(
                        $model
                    )
                ),

            'auditable_type' =>
                $model::class,

            'auditable_id' =>
                $model->getKey(),

            'auditable_code' =>
                $code,

            'description' =>
                $this->description(
                    $model,
                    $action,
                    $code
                ),

            'old_values' =>
                $oldValues,

            'new_values' =>
                $newValues,

            'request_method' =>
                $request->method(),

            'request_path' =>
                $request->path(),

            'route_name' =>
                $request->route()
                    ?->getName(),

            'ip_address' =>
                $request->ip(),

            'user_agent' =>
                $request->userAgent(),
        ]);
    }

    private function recordCode(
        Model $model
    ): ?string {
        $attributes =
            $model->getAttributes();

        $preferred = [
            'code',
            'approval_code',
            'payroll_code',
            'expense_code',
            'transaction_code',
            'purchase_code',
            'collection_code',
            'trip_code',
            'reception_code',
            'lot_code',
            'batch_code',
        ];

        foreach ($preferred as $key) {
            if (
                !empty(
                    $attributes[$key]
                )
            ) {
                return (string)
                    $attributes[$key];
            }
        }

        foreach (
            $attributes
            as $key => $value
        ) {
            if (
                str_ends_with(
                    $key,
                    '_code'
                ) &&
                $value !== null
            ) {
                return (string) $value;
            }
        }

        return null;
    }

    private function description(
        Model $model,
        string $action,
        ?string $code
    ): string {
        $modelName =
            Str::headline(
                class_basename(
                    $model
                )
            );

        $description =
            ucfirst($action) .
            ' ' .
            $modelName;

        if ($code) {
            $description .=
                ' (' .
                $code .
                ')';
        }

        return $description;
    }

    private function clean(
        array $values
    ): array {
        $hidden = [
            'password',
            'remember_token',
            'token',
            'access_token',
            'refresh_token',
        ];

        foreach ($hidden as $key) {
            unset(
                $values[$key]
            );
        }

        return $values;
    }
}
