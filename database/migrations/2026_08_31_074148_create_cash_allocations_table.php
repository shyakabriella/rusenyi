<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_allocations', function (Blueprint $table) {
            $table->id();

            $table->string('allocation_code', 32)
                ->unique();

            $table->foreignId('coffee_season_id')
                ->constrained('coffee_seasons')
                ->restrictOnDelete();

            $table->foreignId('agent_id')
                ->constrained('agents')
                ->restrictOnDelete();

            $table->decimal('amount', 18, 2);

            $table->string('currency', 10)
                ->default('RWF');

            $table->date('allocation_date');

            $table->string('reference', 100)
                ->nullable()
                ->unique();

            $table->text('purpose')
                ->nullable();

            $table->text('notes')
                ->nullable();

            $table->string('status', 20)
                ->default('draft')
                ->index();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('approved_at')
                ->nullable();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('cancelled_at')
                ->nullable();

            $table->text('cancellation_reason')
                ->nullable();

            $table->timestamps();

            $table->index([
                'coffee_season_id',
                'status',
            ]);

            $table->index([
                'agent_id',
                'status',
            ]);

            $table->index([
                'allocation_date',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_allocations');
    }
};
