<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->string('audit_code', 30)
                ->nullable()
                ->unique();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('user_name', 150)
                ->nullable();

            $table->string('user_role', 80)
                ->nullable();

            $table->string('action', 40);

            $table->string('module', 100);

            $table->string('auditable_type', 150);

            $table->unsignedBigInteger('auditable_id');

            $table->string('auditable_code', 100)
                ->nullable();

            $table->string('description', 500);

            $table->json('old_values')
                ->nullable();

            $table->json('new_values')
                ->nullable();

            $table->string('request_method', 10)
                ->nullable();

            $table->string('request_path', 500)
                ->nullable();

            $table->string('route_name', 200)
                ->nullable();

            $table->string('ip_address', 64)
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'created_at',
            ]);

            $table->index([
                'module',
                'action',
            ]);

            $table->index([
                'auditable_type',
                'auditable_id',
            ]);

            $table->index([
                'action',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'audit_logs'
        );
    }
};
