<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();

            $table->string('transaction_code', 30)
                ->nullable()
                ->unique();

            $table->date('transaction_date');

            $table->string('transaction_type', 30);

            $table->decimal('amount', 14, 2);

            $table->decimal('balance_before', 14, 2);

            $table->decimal('balance_after', 14, 2);

            $table->string('currency', 3)
                ->default('RWF');

            $table->string('category', 100)
                ->nullable();

            $table->string('counterparty_name', 150);

            $table->text('purpose');

            $table->string('reference_number', 150)
                ->nullable();

            $table->string('receipt_number', 100)
                ->nullable();

            $table->foreignId('expense_id')
                ->nullable()
                ->unique()
                ->constrained('expenses')
                ->restrictOnDelete();

            $table->foreignId('reverses_transaction_id')
                ->nullable()
                ->unique()
                ->constrained('petty_cash_transactions')
                ->restrictOnDelete();

            $table->string('status', 30)
                ->default('posted');

            $table->text('notes')
                ->nullable();

            $table->foreignId('posted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('posted_at')
                ->nullable();

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
                'transaction_date',
                'status',
            ]);

            $table->index([
                'transaction_type',
                'status',
            ]);

            $table->index([
                'category',
                'status',
            ]);

            $table->index([
                'posted_by',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'petty_cash_transactions'
        );
    }
};
