<?php

namespace Database\Factories;

use App\Models\Accounts;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountsFactory extends Factory
{
    protected $model = Accounts::class;

    public function definition(): array
    {
        return [
            'plant_id' => 1, // Override in tests with a real entity id
            'title'       => fake()->randomElement(Accounts::accountNameType()) . ' - ' . fake()->words(2, true),
            'status'      => 1,
            'created_by'  => null,
            'created'     => now(),
            'modified'    => now(),
            'updated_by' => null,
        ];
    }

    /**
     * Mark the account as soft-deleted.
     */
    public function deleted(): static
    {
        return $this->state(fn () => ['deleted_at' => now()]);
    }

    /**
     * Mark the account as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 0]);
    }
}
