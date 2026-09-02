<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_yields', function (Blueprint $table) {
            $table->id();

            $table->string('yield_code', 30)
                ->nullable()
                ->unique();

            $table->foreignId('processing_batch_id')
                ->constrained('processing_batches')
                ->restrictOnDelete();

            $table->foreignId('store_inventory_id')
                ->constrained('store_inventories')
                ->restrictOnDelete();

            $table->foreignId('source_coffee_lot_id')
                ->constrained('coffee_lots')
                ->restrictOnDelete();

            $table->foreignId('coffee_season_id')
                ->constrained('coffee_seasons')
                ->restrictOnDelete();

            $table->decimal('input_quantity_kg', 12, 2);

            $table->decimal('output_quantity_kg', 12, 2);

            $table->decimal('loss_quantity_kg', 12, 2);

            $table->decimal('yield_percentage', 8, 4);

            $table->decimal('loss_percentage', 8, 4);

            $table->string('output_coffee_type', 50);

            $table->string('output_processing_stage', 30)
                ->default('received');

            $table->unsignedInteger('output_bag_count')
                ->nullable();

            $table->date('yield_date');

            $table->foreignId('output_coffee_lot_id')
                ->nullable()
                ->unique()
                ->constrained('coffee_lots')
                ->restrictOnDelete();

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
                'processing_batch_id',
                'status',
            ]);

            $table->index([
                'coffee_season_id',
                'status',
            ]);

            $table->index([
                'source_coffee_lot_id',
                'status',
            ]);

            $table->index([
                'output_coffee_type',
                'status',
            ]);

            $table->index([
                'yield_date',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_yields');
    }
};
