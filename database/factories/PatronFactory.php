<?php

namespace Database\Factories;

use App\Models\Ledger;
use App\Models\Patron;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatronFactory extends Factory
{
    protected $model = Patron::class;

    public function definition(): array
    {
        return [
            'plant_id' => Plant::factory(),
            'patron_type' => ['Customer'],
            'legal_name' => $this->faker->unique()->company(),
            'ledger_id' => null,
            'operational_status' => 'active',
            'pan_no' => strtoupper($this->faker->bothify('?????#####?')),
            'gstin' => strtoupper($this->faker->bothify('##?????####?#?#')),
            'status' => 1,
            'displayed' => 1,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
