<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'display_name' => 'Admin',
                'description' => 'Factory administrator with access to all system operations.',
            ],
            [
                'name' => 'accountant',
                'display_name' => 'Accountant',
                'description' => 'Manages company funds, farmer payments, agent allocations, expenses and petty cash.',
            ],
            [
                'name' => 'balance',
                'display_name' => 'Balance Officer',
                'description' => 'Measures and records coffee weight from farmers and agents.',
            ],
            [
                'name' => 'agent',
                'display_name' => 'Agent',
                'description' => 'Collects coffee from farmers and rural collection areas using allocated company funds.',
            ],
            [
                'name' => 'driver',
                'display_name' => 'Driver',
                'description' => 'Transports collected coffee from collection areas to the factory.',
            ],
            [
                'name' => 'store',
                'display_name' => 'Store Officer',
                'description' => 'Receives coffee into store, manages stock and related store operations.',
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                [
                    'name' => $role['name'],
                ],
                [
                    'display_name' => $role['display_name'],
                    'description' => $role['description'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
