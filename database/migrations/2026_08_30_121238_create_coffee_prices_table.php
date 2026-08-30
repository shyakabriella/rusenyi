<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coffee_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coffee_season_id')
                ->constrained('coffee_seasons')
                ->restrictOnDelete();

            $table->string('code', 50)
                ->unique();

            $table->string('coffee_type', 50)
                ->index();

            $table->decimal(
                'price_per_kg',
                15,
                2
            );

            $table->string('currency', 10)
                ->default('RWF');

            $table->date('effective_from');

            $table->date('effective_to')
                ->nullable();

            $table->string('status', 20)
                ->default('draft')
                ->index();

            $table->text('notes')
                ->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('activated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('activated_at')
                ->nullable();

            $table->foreignId('deactivated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('deactivated_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'coffee_season_id',
                'coffee_type',
                'status',
            ]);

            $table->unique([
                'coffee_season_id',
                'coffee_type',
                'effective_from',
            ], 'coffee_price_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'coffee_prices'
        );
    }
};
