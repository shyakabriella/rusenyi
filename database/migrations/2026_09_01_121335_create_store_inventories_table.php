<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_inventories', function (Blueprint $table) {
            $table->id();

            $table->string('inventory_code', 30)
                ->nullable()
                ->unique();

            $table->foreignId('coffee_lot_id')
                ->constrained('coffee_lots')
                ->restrictOnDelete();

            $table->foreignId('coffee_season_id')
                ->constrained('coffee_seasons')
                ->restrictOnDelete();

            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id');

            $table->string('coffee_type', 50);

            $table->decimal('initial_quantity_kg', 12, 2);
            $table->decimal('current_quantity_kg', 12, 2);

            $table->unsignedInteger('bag_count')
                ->nullable();

            $table->string('storage_location', 150);

            $table->dateTime('received_at');

            $table->string('status', 30)
                ->default('active');

            $table->text('notes')
                ->nullable();

            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

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
                'coffee_lot_id',
                'status',
            ]);

            $table->index([
                'coffee_season_id',
                'status',
            ]);

            $table->index([
                'coffee_type',
                'status',
            ]);

            $table->index([
                'storage_location',
                'status',
            ]);

            $table->index([
                'source_type',
                'source_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'store_inventories'
        );
    }
};
