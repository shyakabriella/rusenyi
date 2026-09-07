<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasColumn(
                'petty_cash_transactions',
                'accountant_id'
            )
        ) {
            Schema::table(
                'petty_cash_transactions',
                function (Blueprint $table) {
                    $table->foreignId('accountant_id')
                        ->nullable()
                        ->after('transaction_code')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            );
        }

        if (
            !Schema::hasColumn(
                'petty_cash_transactions',
                'reference_type'
            )
        ) {
            Schema::table(
                'petty_cash_transactions',
                function (Blueprint $table) {
                    $table->string(
                        'reference_type',
                        50
                    )
                        ->nullable()
                        ->after('receipt_number');

                    $table->unsignedBigInteger(
                        'reference_id'
                    )
                        ->nullable()
                        ->after('reference_type');

                    $table->index(
                        [
                            'reference_type',
                            'reference_id',
                        ],
                        'petty_cash_reference_index'
                    );

                    $table->index(
                        [
                            'accountant_id',
                            'transaction_date',
                        ],
                        'petty_cash_accountant_date_index'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'petty_cash_transactions',
                'reference_type'
            )
        ) {
            Schema::table(
                'petty_cash_transactions',
                function (Blueprint $table) {
                    $table->dropIndex(
                        'petty_cash_reference_index'
                    );

                    $table->dropIndex(
                        'petty_cash_accountant_date_index'
                    );

                    $table->dropColumn([
                        'reference_type',
                        'reference_id',
                    ]);
                }
            );
        }

        if (
            Schema::hasColumn(
                'petty_cash_transactions',
                'accountant_id'
            )
        ) {
            Schema::table(
                'petty_cash_transactions',
                function (Blueprint $table) {
                    $table->dropConstrainedForeignId(
                        'accountant_id'
                    );
                }
            );
        }
    }
};
