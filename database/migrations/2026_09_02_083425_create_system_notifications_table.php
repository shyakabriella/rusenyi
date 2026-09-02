<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();

            $table->string('notification_code', 30)
                ->nullable()
                ->unique();

            $table->foreignId('recipient_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('type', 80);

            $table->string('title', 200);

            $table->text('message');

            $table->string('module', 80)
                ->nullable();

            $table->string('reference_type', 80)
                ->nullable();

            $table->unsignedBigInteger('reference_id')
                ->nullable();

            $table->string('reference_code', 80)
                ->nullable();

            $table->string('action_url', 500)
                ->nullable();

            $table->json('data')
                ->nullable();

            $table->timestamp('read_at')
                ->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'recipient_id',
                'read_at',
            ]);

            $table->index([
                'recipient_id',
                'created_at',
            ]);

            $table->index([
                'module',
                'type',
            ]);

            $table->index([
                'reference_type',
                'reference_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'system_notifications'
        );
    }
};
