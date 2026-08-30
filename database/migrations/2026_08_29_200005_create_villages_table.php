<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cell_id')
                ->constrained('cells')
                ->restrictOnDelete();

            $table->string('name');

            $table->boolean('is_active')
                ->default(true)
                ->index();

            $table->timestamps();

            $table->unique([
                'cell_id',
                'name',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
    }
};
