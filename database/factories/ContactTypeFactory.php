<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ContactType;

class ContactTypeFactory extends Factory
{
    protected $model = ContactType::class;

    public function definition(): array
    {
        return [
            'type' => fake()->unique()->word(),
        ];
    }
}