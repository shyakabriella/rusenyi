<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factory_receptions', function (Blueprint $table) {
            $table->id();

            $table->string('reception_code', 30)
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

            // Added later when Collection Trips is built.
            $table->unsignedBigInteger('collection_trip_id')
                ->nullable();

            $table->foreignId('balance_officer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->decimal('field_weight_kg', 12, 2);
            $table->decimal('factory_weight_kg', 12, 2);
            $table->decimal('difference_kg', 12, 2);

            $table->decimal(
                'difference_percentage',
                8,
                4
            )->default(0);

            $table->unsignedInteger('bag_count')
                ->nullable();

            $table->dateTime('received_at');

            $table->string('status', 20)
                ->default('draft');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('confirmed_at')
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
                'agent_collection_id',
                'status',
            ]);

            $table->index([
                'coffee_season_id',
                'received_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factory_receptions');
    }
};
