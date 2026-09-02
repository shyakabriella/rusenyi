<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreignId('payment_approval_id')
                ->nullable()
                ->unique()
                ->after('status')
                ->constrained('approval_requests')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropConstrainedForeignId(
                'payment_approval_id'
            );
        });
    }
};
