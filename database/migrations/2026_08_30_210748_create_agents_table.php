<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();

            $table->string('agent_code', 32)
                ->unique();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('home_village_id')
                ->nullable()
                ->constrained('villages')
                ->restrictOnDelete();

            $table->string('status', 20)
                ->default('active')
                ->index();

            $table->text('notes')
                ->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('deactivated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('deactivated_at')
                ->nullable();

            $table->foreignId('reactivated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('reactivated_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'home_village_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
