<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coffee_purchases', function (Blueprint $table) {
            $table->id();

            $table->string('purchase_code', 30)
                ->nullable()
                ->unique();

            $table->foreignId('coffee_season_id')
                ->constrained('coffee_seasons')
                ->restrictOnDelete();

            $table->foreignId('coffee_price_id')
                ->constrained('coffee_prices')
                ->restrictOnDelete();

            $table->foreignId('agent_id')
                ->constrained('agents')
                ->restrictOnDelete();

            $table->foreignId('farmer_id')
                ->constrained('farmers')
                ->restrictOnDelete();

            $table->foreignId('collection_point_id')
                ->nullable()
                ->constrained('collection_points')
                ->restrictOnDelete();

            $table->string('coffee_type', 50);

            $table->decimal('quantity_kg', 12, 2);
            $table->decimal('price_per_kg', 18, 2);
            $table->decimal('total_amount', 18, 2);

            $table->string('currency', 10)
                ->default('RWF');

            $table->date('purchase_date');

            $table->string('status', 20)
                ->default('draft');

            $table->text('purpose')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')
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
                'agent_id',
                'status',
                'purchase_date',
            ]);

            $table->index([
                'farmer_id',
                'purchase_date',
            ]);

            $table->index([
                'coffee_season_id',
                'coffee_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coffee_purchases');
    }
};
