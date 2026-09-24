<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Role;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => fake()->jobTitle(),
            'code' => fake()->unique()->slug(2),
            'guard_name' => 'web',
            'description' => fake()->sentence(),
            'level' => 1,
            'is_system' => 0,
            'status' => 'active',
        ];
    }
}
