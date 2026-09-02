<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weight_reconciliations', function (Blueprint $table) {
            $table->id();

            $table->string('reconciliation_code', 30)
                ->nullable()
                ->unique();

            $table->foreignId('factory_reception_id')
                ->constrained('factory_receptions')
                ->restrictOnDelete();

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

            $table->decimal('field_weight_kg', 12, 2);
            $table->decimal('factory_weight_kg', 12, 2);

            $table->decimal('difference_kg', 12, 2);
            $table->decimal('difference_percentage', 8, 4);

            $table->decimal('tolerance_percentage', 5, 2)
                ->default(2.00);

            $table->string('outcome', 30)
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

            $table->foreignId('reconciled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reconciled_at')
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
                'factory_reception_id',
                'status',
            ]);

            $table->index([
                'coffee_season_id',
                'status',
            ]);

            $table->index([
                'agent_id',
                'status',
            ]);

            $table->index([
                'outcome',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'weight_reconciliations'
        );
    }
};
