<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'worker_id')) {
                $table->foreignId('worker_id')
                    ->nullable()
                    ->after('employee_id')
                    ->constrained('workers')
                    ->nullOnDelete();
            }
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'worker_id')) {
                $table->dropConstrainedForeignId('worker_id');
            }
        });
    }
};
