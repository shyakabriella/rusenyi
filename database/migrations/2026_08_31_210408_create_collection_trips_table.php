<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_trips', function (Blueprint $table) {
            $table->id();

            $table->string('trip_code', 30)
                ->nullable()
                ->unique();

            $table->foreignId('coffee_season_id')
                ->constrained('coffee_seasons')
                ->restrictOnDelete();

            $table->foreignId('agent_collection_id')
                ->constrained('agent_collections')
                ->restrictOnDelete();

            $table->foreignId('field_weighing_id')
                ->constrained('field_weighings')
                ->restrictOnDelete();

            $table->foreignId('agent_id')
                ->constrained('agents')
                ->restrictOnDelete();

            $table->foreignId('collection_point_id')
                ->nullable()
                ->constrained('collection_points')
                ->restrictOnDelete();

            $table->foreignId('driver_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('vehicle_registration', 50)
                ->nullable();

            $table->decimal(
                'field_weight_kg',
                12,
                2
            );

            $table->string('status', 30)
                ->default('planned');

            $table->dateTime('departure_at')
                ->nullable();

            $table->dateTime('arrived_at')
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

            $table->foreignId('started_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('arrived_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('completed_at')
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
                'field_weighing_id',
                'status',
            ]);

            $table->index([
                'driver_user_id',
                'status',
            ]);

            $table->index([
                'agent_id',
                'status',
            ]);

            $table->index([
                'coffee_season_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'collection_trips'
        );
    }
};
