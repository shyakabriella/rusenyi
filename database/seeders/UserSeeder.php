<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'admin@gihombo.com',
            ],
            [
                'name' => 'CYUSA Patrick',
                'phone' => '0788241224',
                'role' => 'admin',
                'password' => 'Gihombo@123',
                'status' => 'active',
                'is_active' => true,
                'must_change_password' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
