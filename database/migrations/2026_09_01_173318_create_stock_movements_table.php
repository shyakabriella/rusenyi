<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->string('movement_code', 30)
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

            $table->string('movement_type', 40);

            $table->decimal('quantity_kg', 12, 2)
                ->default(0);

            $table->decimal('quantity_before_kg', 12, 2);

            $table->decimal('quantity_after_kg', 12, 2);

            $table->string('from_location', 150)
                ->nullable();

            $table->string('to_location', 150)
                ->nullable();

            $table->string('reference_type', 80)
                ->nullable();

            $table->unsignedBigInteger('reference_id')
                ->nullable();

            $table->unsignedBigInteger('reverses_stock_movement_id')
                ->nullable();

            $table->text('reason');

            $table->text('notes')
                ->nullable();

            $table->string('status', 30)
                ->default('posted');

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
                'movement_type',
                'status',
            ]);

            $table->index([
                'reference_type',
                'reference_id',
            ]);

            $table->index(
                'reverses_stock_movement_id'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'stock_movements'
        );
    }
};
