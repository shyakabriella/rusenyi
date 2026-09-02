<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();

            $table->string('payroll_code', 30)
                ->nullable()
                ->unique();

            $table->foreignId('employee_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('employee_name', 150);

            $table->string('employee_role', 50)
                ->nullable();

            $table->string('payroll_month', 7);

            $table->decimal('basic_salary', 14, 2);

            $table->decimal('allowances', 14, 2)
                ->default(0);

            $table->decimal('gross_salary', 14, 2);

            $table->decimal('deductions', 14, 2)
                ->default(0);

            $table->decimal('net_salary', 14, 2);

            $table->string('currency', 3)
                ->default('RWF');

            $table->string('status', 30)
                ->default('draft');

            $table->string('payment_method', 30)
                ->nullable();

            $table->string('payment_reference', 150)
                ->nullable();

            $table->date('payment_date')
                ->nullable();

            $table->text('notes')
                ->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('processed_at')
                ->nullable();

            $table->foreignId('paid_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('paid_at')
                ->nullable();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('cancelled_at')
                ->nullable();

            $table->text('cancellation_reason')
                ->nullable();

            $table->timestamps();

            $table->index([
                'employee_id',
                'payroll_month',
            ]);

            $table->index([
                'payroll_month',
                'status',
            ]);

            $table->index([
                'status',
                'payment_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
