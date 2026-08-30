<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'agent_collection_points',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId(
                    'collection_point_id'
                )
                    ->constrained(
                        'collection_points'
                    )
                    ->restrictOnDelete();

                $table->boolean('is_active')
                    ->default(true)
                    ->index();

                $table->timestamp('assigned_at')
                    ->nullable();

                $table->timestamp('unassigned_at')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'user_id',
                    'collection_point_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'agent_collection_points'
        );
    }
};
