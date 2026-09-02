<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_collections', function (Blueprint $table) {
            $table->id();

            $table->string('collection_code', 30)
                ->nullable()
                ->unique();

            $table->foreignId('coffee_season_id')
                ->constrained('coffee_seasons')
                ->restrictOnDelete();

            $table->foreignId('agent_id')
                ->constrained('agents')
                ->restrictOnDelete();

            $table->foreignId('collection_point_id')
                ->nullable()
                ->constrained('collection_points')
                ->restrictOnDelete();

            $table->date('collection_date');

            $table->string('status', 20)
                ->default('open');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
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
                'agent_id',
                'status',
                'collection_date',
            ]);

            $table->index([
                'coffee_season_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_collections');
    }
};
