<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            $table->string('vehicle_code', 30)
                ->nullable()
                ->unique();

            $table->string('registration_number', 50)
                ->unique();

            $table->string('vehicle_type', 50);

            $table->string('make', 80)
                ->nullable();

            $table->string('model', 80)
                ->nullable();

            $table->unsignedSmallInteger('manufacture_year')
                ->nullable();

            $table->decimal('capacity_kg', 12, 2)
                ->nullable();

            $table->foreignId('assigned_driver_id')
                ->nullable()
                ->unique()
                ->constrained('drivers')
                ->nullOnDelete();

            $table->string('status', 30)
                ->default('available');

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

            $table->index('status');
            $table->index('vehicle_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
