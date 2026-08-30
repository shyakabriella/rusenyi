<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_points', function (Blueprint $table) {
            $table->id();

            $table->foreignId('village_id')
                ->constrained('villages')
                ->restrictOnDelete();

            $table->string('name');

            $table->string('code')
                ->unique();

            $table->decimal(
                'latitude',
                10,
                7
            )->nullable();

            $table->decimal(
                'longitude',
                10,
                7
            )->nullable();

            $table->text('description')
                ->nullable();

            $table->boolean('is_active')
                ->default(true)
                ->index();

            $table->timestamps();

            $table->unique([
                'village_id',
                'name',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'collection_points'
        );
    }
};
