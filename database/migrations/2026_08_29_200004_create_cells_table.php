<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cells', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sector_id')
                ->constrained('sectors')
                ->restrictOnDelete();

            $table->string('name');

            $table->boolean('is_active')
                ->default(true)
                ->index();

            $table->timestamps();

            $table->unique([
                'sector_id',
                'name',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cells');
    }
};
