<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

class UserFactory extends Factory
{
    protected $model = User::class;
    
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->name(),
            'password' => static::$password ??= \Illuminate\Support\Facades\Hash::make('password'),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => fake()->word(),
            'email_verified_at' => now(),
            'is_active' => 1,
            'is_otp_enabled' => 0,
            'failed_attempts' => 0,
            'last_login' => null,
            'last_visit_page' => null,
            'ip_address' => fake()->ipv4(),
            'lockout_until' => null,
            'otp_secret' => null,
            'profile_photo_path' => null,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
