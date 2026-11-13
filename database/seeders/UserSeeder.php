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

        // 2. Create Curie and staff users
        $curieUsers = [
            [
                'email' => 'curieadmin@onemodo.com',
                'username' => 'curieadmin',
                'password' => Hash::make('Test@123'),
                'role' => 'ADMINISTRATOR',
            ],
            [
                'email' => 'curiesales@onemodo.com',
                'username' => 'curiesales',
                'password' => Hash::make('Test@123'),
                'role' => 'SALES_EXECUTIVE',
            ],
            [
                'email' => 'prabakaran@onemodo.com',
                'username' => 'prabakaran',
                'password' => Hash::make('Test@123'),
                'role' => 'ADMINISTRATOR',
            ],
        ];

        foreach ($curieUsers as $u) {
            $createdUser = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'username' => $u['username'],
                    'password' => $u['password'],
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'is_otp_enabled' => true,
                ]
            );

            if (!empty($u['role'])) {
                $userRole = \App\Models\Role::where('code', $u['role'])->first();
                if ($userRole) {
                    $createdUser->assignRole($userRole);
                }
            }
        }

        // 3. Create some demo users
        User::factory()->count(10)->create();
    }
}