<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->string('transaction_code')->nullable()->unique();

            $table->foreignId('agent_id')
                ->constrained('agents')
                ->restrictOnDelete();

            $table->foreignId('coffee_season_id')
                ->nullable()
                ->constrained('coffee_seasons')
                ->restrictOnDelete();

            $table->string('type', 50);
            $table->string('direction', 10);

            $table->decimal('amount', 18, 2);
            $table->string('currency', 10)->default('RWF');

            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id');

            $table->string('description')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['source_type', 'source_id', 'type'],
                'wallet_source_transaction_unique'
            );

            $table->index(['agent_id', 'coffee_season_id']);
            $table->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_wallet_transactions');
    }
};
