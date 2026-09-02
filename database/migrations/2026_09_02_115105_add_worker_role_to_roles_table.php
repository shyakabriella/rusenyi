<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $exists = DB::table('roles')
            ->where('name', 'worker')
            ->exists();

        if ($exists) {
            return;
        }

        $data = [
            'name' => 'worker',
            'display_name' => 'Worker',
            'is_active' => true,
        ];

        if (Schema::hasColumn('roles', 'description')) {
            $data['description'] =
                'Factory worker eligible for payroll.';
        }

        if (Schema::hasColumn('roles', 'created_at')) {
            $data['created_at'] = now();
        }

        if (Schema::hasColumn('roles', 'updated_at')) {
            $data['updated_at'] = now();
        }

        DB::table('roles')->insert($data);
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')
            ->where('name', 'worker')
            ->delete();
    }
};
