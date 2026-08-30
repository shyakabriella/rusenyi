<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coffee_seasons', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150)->unique();
            $table->string('code', 50)->unique();

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->string('status', 20)
                ->default('draft')
                ->index();

            $table->text('description')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('activated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('activated_at')->nullable();

            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index([
                'start_date',
                'end_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coffee_seasons');
    }
};
