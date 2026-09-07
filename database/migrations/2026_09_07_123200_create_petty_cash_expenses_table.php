<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_expenses', function (Blueprint $table) {
            $table->id();

            $table->string('expense_code')
                ->nullable()
                ->unique();

            $table->foreignId('accountant_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('expense_date');

            $table->string('category', 120);

            $table->string('payee')
                ->nullable();

            $table->decimal('amount', 18, 2);

            $table->string('currency', 10)
                ->default('RWF');

            $table->text('description');

            $table->string('receipt_path')
                ->nullable();

            $table->string('receipt_name')
                ->nullable();

            $table->string('status', 20)
                ->default('posted');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('reversed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reversed_at')
                ->nullable();

            $table->text('reversal_reason')
                ->nullable();

            $table->timestamps();

            $table->index([
                'accountant_id',
                'expense_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'petty_cash_expenses'
        );
    }
};
