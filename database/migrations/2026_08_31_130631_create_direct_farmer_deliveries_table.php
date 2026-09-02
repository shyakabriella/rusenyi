<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_farmer_deliveries', function (Blueprint $table) {
            $table->id();

            $table->string('delivery_code', 30)
                ->nullable()
                ->unique();

            $table->foreignId('coffee_season_id')
                ->constrained('coffee_seasons')
                ->restrictOnDelete();

            $table->foreignId('coffee_price_id')
                ->constrained('coffee_prices')
                ->restrictOnDelete();

            $table->foreignId('farmer_id')
                ->constrained('farmers')
                ->restrictOnDelete();

            $table->foreignId('balance_officer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('coffee_type', 50);

            $table->decimal('quantity_kg', 12, 2);
            $table->decimal('price_per_kg', 18, 2);
            $table->decimal('total_amount', 18, 2);

            $table->string('currency', 10)
                ->default('RWF');

            $table->date('delivery_date');

            $table->string('status', 20)
                ->default('draft');

            $table->string('payment_status', 20)
                ->default('unpaid');

            $table->string('payment_method', 30)
                ->nullable();

            $table->string('payment_reference', 120)
                ->nullable()
                ->unique();

            $table->string('payment_proof_path')
                ->nullable();

            $table->string('payment_proof_original_name')
                ->nullable();

            $table->string('payment_proof_mime_type', 100)
                ->nullable();

            $table->unsignedBigInteger('payment_proof_size')
                ->nullable();

            $table->foreignId('payment_proof_uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('payment_proof_uploaded_at')
                ->nullable();

            $table->text('purpose')
                ->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('confirmed_at')
                ->nullable();

            $table->foreignId('paid_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('paid_at')
                ->nullable();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('cancelled_at')
                ->nullable();

            $table->text('cancellation_reason')
                ->nullable();

            $table->timestamps();

            $table->index([
                'farmer_id',
                'delivery_date',
            ]);

            $table->index([
                'coffee_season_id',
                'status',
            ]);

            $table->index([
                'payment_status',
                'delivery_date',
            ]);

            $table->index([
                'balance_officer_id',
                'delivery_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_farmer_deliveries');
    }
};
