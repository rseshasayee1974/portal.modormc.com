<?php

namespace Database\Factories;

use App\Models\AccountsType;
use App\Models\Ledger;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LedgerFactory extends Factory
{
    protected $model = Ledger::class;

    public function definition(): array
    {
        $title = $this->faker->words(3, true);
        return [
            'plant_id' => Plant::factory(),
            'account_type_id' => AccountsType::factory(),
            'code' => $this->faker->unique()->numerify('####'),
            'title' => $title,
            'slug' => Str::slug($title),
            'is_pnl' => $this->faker->boolean(),
            'status' => 1,
            'created_at' => now(),
        ];
    }
}
