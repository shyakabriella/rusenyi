<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->string('expense_code', 30)
                ->nullable()
                ->unique();

            $table->date('expense_date');

            $table->string('category', 100);

            $table->string('payee_name', 150);

            $table->text('description');

            $table->decimal('amount', 14, 2);

            $table->string('currency', 3)
                ->default('RWF');

            $table->string('payment_method', 30);

            $table->string('payment_reference', 150)
                ->nullable();

            $table->string('receipt_number', 100)
                ->nullable();

            $table->string('source_type', 50)
                ->nullable();

            $table->unsignedBigInteger('source_id')
                ->nullable();

            $table->string('status', 30)
                ->default('draft');

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

            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('recorded_at')
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
                'expense_date',
                'status',
            ]);

            $table->index([
                'category',
                'status',
            ]);

            $table->index([
                'payment_method',
                'status',
            ]);

            $table->index([
                'created_by',
                'status',
            ]);

            $table->index([
                'source_type',
                'source_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
