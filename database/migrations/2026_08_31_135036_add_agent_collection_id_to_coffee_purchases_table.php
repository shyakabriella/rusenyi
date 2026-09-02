<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coffee_purchases', function (Blueprint $table) {
            $table->foreignId('agent_collection_id')
                ->nullable()
                ->after('collection_point_id')
                ->constrained('agent_collections')
                ->restrictOnDelete();

            $table->index([
                'agent_collection_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('coffee_purchases', function (Blueprint $table) {
            $table->dropForeign([
                'agent_collection_id',
            ]);

            $table->dropIndex([
                'agent_collection_id',
                'status',
            ]);

            $table->dropColumn(
                'agent_collection_id'
            );
        });
    }
};
