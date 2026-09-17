<?php

namespace Database\Factories\QC;

use App\Models\QC\QcUnit;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Plant;
use App\Models\User;

/**
 * @extends Factory<QcUnit>
 */
class QcUnitFactory extends Factory
{
    protected $model = QcUnit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plant_id' => Plant::factory(),
            'code' => $this->faker->unique()->lexify('UNIT_????'),
            'name' => $this->faker->words(2, true),
            'symbol' => $this->faker->lexify('??'),
            'dimension' => 'Force',
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
