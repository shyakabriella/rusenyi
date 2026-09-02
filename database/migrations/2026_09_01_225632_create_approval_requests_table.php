<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();

            $table->string('approval_code', 30)
                ->nullable()
                ->unique();

            $table->string('module', 50);
            $table->string('action', 50);

            $table->string('reference_type', 80);
            $table->unsignedBigInteger('reference_id');

            $table->string('reference_code', 80)
                ->nullable();

            $table->string('title', 200);

            $table->text('description')
                ->nullable();

            $table->decimal('amount', 14, 2)
                ->nullable();

            $table->string('currency', 3)
                ->default('RWF');

            $table->string('status', 30)
                ->default('pending');

            $table->text('request_note')
                ->nullable();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('requested_at');

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')
                ->nullable();

            $table->text('review_note')
                ->nullable();

            $table->foreignId('applied_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('applied_at')
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
                'module',
                'status',
            ]);

            $table->index([
                'reference_type',
                'reference_id',
            ]);

            $table->index([
                'action',
                'status',
            ]);

            $table->index([
                'requested_by',
                'status',
            ]);

            $table->index([
                'requested_at',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
