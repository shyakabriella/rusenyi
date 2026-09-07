<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_requests', function (Blueprint $table) {
            $table->id();

            $table->string('request_code')
                ->nullable()
                ->unique();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->decimal('amount', 18, 2);

            $table->string('currency', 10)
                ->default('RWF');

            $table->text('purpose');

            $table->string('status', 20)
                ->default('pending');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')
                ->nullable();

            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('rejected_at')
                ->nullable();

            $table->text('rejection_reason')
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
                'requested_by',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'petty_cash_requests'
        );
    }
};
