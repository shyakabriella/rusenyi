<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('worker_id')
                ->constrained('workers')
                ->cascadeOnDelete();

            $table->date('attendance_date');

            $table->string('status', 20);

            $table->string('notes', 500)
                ->nullable();

            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'worker_id',
                'attendance_date',
            ]);

            $table->index([
                'attendance_date',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_attendances');
    }
};
