<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactType;
use App\Models\Patron;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'plant_id' => Plant::factory(),
            'patron_id' => Patron::factory(),
            'contact_type_id' => ContactType::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'mobile' => $this->faker->numerify('##########'),
            'is_primary' => true,
            'status' => true,
            'displayed' => true,
        ];
    }
}
