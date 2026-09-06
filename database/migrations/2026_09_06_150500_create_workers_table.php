<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();

            $table->string('worker_code', 30)
                ->unique();

            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');

            $table->string('phone', 40)
                ->nullable()
                ->unique();

            $table->string('email')
                ->nullable()
                ->unique();

            $table->string('national_id', 100)
                ->nullable()
                ->unique();

            $table->string('status', 20)
                ->default('active')
                ->index();

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

            $table->foreignId('status_changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('status_changed_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'name',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
