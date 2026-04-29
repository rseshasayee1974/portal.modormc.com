<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create a primary admin user
        $user = User::updateOrCreate(
            ['email' => 'ragul@onemodo.com'],
            [
                'username' => 'ragul',
                'password' => Hash::make('123456789'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // Assign SaaS Owner role
        $role = \App\Models\Role::where('code', 'SAAS_OWNER')->first();
        if ($role) {
            $user->assignRole($role);
        }

        // 2. Create some demo users
        User::factory()->count(10)->create();
    }
}