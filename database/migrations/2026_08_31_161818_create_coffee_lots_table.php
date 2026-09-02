<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coffee_lots', function (Blueprint $table) {
            $table->id();

            $table->string('lot_code', 30)
                ->nullable()
                ->unique();

            $table->foreignId('coffee_season_id')
                ->constrained('coffee_seasons')
                ->restrictOnDelete();

            $table->string('source_type', 40);

            $table->unsignedBigInteger('source_id');

            $table->string('coffee_type', 30);

            $table->decimal(
                'initial_weight_kg',
                12,
                2
            );

            $table->decimal(
                'current_weight_kg',
                12,
                2
            );

            $table->unsignedInteger('bag_count')
                ->nullable();

            $table->string(
                'processing_stage',
                40
            )->default('received');

            $table->string(
                'status',
                20
            )->default('active');

            $table->string(
                'storage_location',
                150
            )->nullable();

            $table->date('lot_date');

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

            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('closed_at')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'source_type',
                'source_id',
            ]);

            $table->index([
                'coffee_season_id',
                'status',
            ]);

            $table->index([
                'processing_stage',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coffee_lots');
    }
};
