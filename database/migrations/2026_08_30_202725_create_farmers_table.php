<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();

            $table->string('farmer_code', 32)
                ->unique();

            $table->string('full_name', 150)
                ->index();

            $table->string('phone', 30)
                ->unique();

            $table->string('national_id', 50)
                ->nullable()
                ->unique();

            $table->string('gender', 20)
                ->nullable();

            $table->foreignId('village_id')
                ->constrained('villages')
                ->restrictOnDelete();

            $table->foreignId('collection_point_id')
                ->nullable()
                ->constrained('collection_points')
                ->restrictOnDelete();

            $table->string(
                'preferred_payment_method',
                30
            )->default('cash');

            $table->text('address_note')
                ->nullable();

            $table->text('notes')
                ->nullable();

            $table->string('status', 20)
                ->default('active')
                ->index();

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
                'village_id',
                'status',
            ]);

            $table->index([
                'collection_point_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmers');
    }
};
