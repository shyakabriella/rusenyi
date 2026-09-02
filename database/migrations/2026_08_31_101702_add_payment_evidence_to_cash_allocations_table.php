<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_allocations', function (Blueprint $table) {
            /*
             * Nullable is intentional so allocations created
             * before this upgrade remain valid historical data.
             *
             * All NEW allocations will receive a payment method
             * through request validation.
             */
            $table->string('payment_method', 30)
                ->nullable()
                ->after('currency')
                ->index();

            $table->string('payment_proof_path')
                ->nullable()
                ->after('notes');

            $table->string('payment_proof_original_name')
                ->nullable()
                ->after('payment_proof_path');

            $table->string('payment_proof_mime_type', 100)
                ->nullable()
                ->after('payment_proof_original_name');

            $table->unsignedBigInteger('payment_proof_size')
                ->nullable()
                ->after('payment_proof_mime_type');

            $table->foreignId('payment_proof_uploaded_by')
                ->nullable()
                ->after('payment_proof_size')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('payment_proof_uploaded_at')
                ->nullable()
                ->after('payment_proof_uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('cash_allocations', function (Blueprint $table) {
            $table->dropForeign([
                'payment_proof_uploaded_by',
            ]);

            $table->dropColumn([
                'payment_method',
                'payment_proof_path',
                'payment_proof_original_name',
                'payment_proof_mime_type',
                'payment_proof_size',
                'payment_proof_uploaded_by',
                'payment_proof_uploaded_at',
            ]);
        });
    }
};
