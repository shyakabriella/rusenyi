<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_batches', function (Blueprint $table) {
            $table->id();

            $table->string('batch_code', 30)
                ->nullable()
                ->unique();

            $table->foreignId('store_inventory_id')
                ->constrained('store_inventories')
                ->restrictOnDelete();

            $table->foreignId('coffee_lot_id')
                ->constrained('coffee_lots')
                ->restrictOnDelete();

            $table->foreignId('coffee_season_id')
                ->constrained('coffee_seasons')
                ->restrictOnDelete();

            $table->string('process_name', 100);

            $table->decimal(
                'input_quantity_kg',
                12,
                2
            );

            $table->string(
                'source_storage_location',
                150
            );

            $table->timestamp('planned_start_at')
                ->nullable();

            $table->string('status', 30)
                ->default('draft');

            $table->foreignId(
                'processing_issue_movement_id'
            )
                ->nullable()
                ->unique()
                ->constrained(
                    'stock_movements'
                )
                ->restrictOnDelete();

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

            $table->timestamp('started_at')
                ->nullable();

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
                'store_inventory_id',
                'status',
            ]);

            $table->index([
                'coffee_lot_id',
                'status',
            ]);

            $table->index([
                'coffee_season_id',
                'status',
            ]);

            $table->index([
                'process_name',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'processing_batches'
        );
    }
};
