<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_pickups', function (Blueprint $table) {
            $table->id();

            $table->string('pickup_code', 40)->unique();
            $table->string('receipt_code', 40)->nullable()->unique();

            $table->foreignId('agent_collection_id')
                ->constrained('agent_collections')
                ->restrictOnDelete();

            $table->foreignId('agent_id')
                ->constrained('agents')
                ->restrictOnDelete();

            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->restrictOnDelete();

            $table->string('vehicle_registration', 80)->nullable();

            $table->decimal(
                'declared_quantity_kg',
                14,
                2
            );

            $table->decimal(
                'pickup_weight_kg',
                14,
                2
            )->nullable();

            $table->decimal(
                'pickup_difference_kg',
                14,
                2
            )->nullable();

            $table->decimal(
                'factory_weight_kg',
                14,
                2
            )->nullable();

            $table->decimal(
                'factory_difference_kg',
                14,
                2
            )->nullable();

            $table->string('status', 40)
                ->default('pending');

            $table->text('request_note')->nullable();
            $table->text('response_note')->nullable();
            $table->text('weight_note')->nullable();
            $table->text('factory_note')->nullable();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('requested_at')->nullable();

            $table->foreignId('accepted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('accepted_at')->nullable();

            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('rejected_at')->nullable();

            $table->foreignId('pickup_confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('pickup_confirmed_at')->nullable();

            $table->timestamp('departed_at')->nullable();
            $table->timestamp('arrived_at')->nullable();

            $table->foreignId('factory_weighed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('factory_weighed_at')->nullable();

            $table->foreignId('factory_acknowledged_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('factory_acknowledged_at')
                ->nullable();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();

            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index([
                'agent_collection_id',
                'status',
            ]);

            $table->index([
                'agent_id',
                'status',
            ]);

            $table->index([
                'driver_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_pickups');
    }
};
