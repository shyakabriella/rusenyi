<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('district_id')
                ->constrained('districts')
                ->restrictOnDelete();

            $table->string('name');

            $table->boolean('is_active')
                ->default(true)
                ->index();

            $table->timestamps();

            $table->unique([
                'district_id',
                'name',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};
