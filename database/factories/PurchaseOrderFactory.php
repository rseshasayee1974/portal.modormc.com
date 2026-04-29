<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entity = Entity::inRandomOrder()->first();
        return [
            'plant_id' => 1,
            'plant_id' => Plant::where('entity_id', $entity ? $entity->id : 1)->inRandomOrder()->first()?->id ?? 1,
            'vendor_id' => Patron::where('entity_id', $entity ? $entity->id : 1)->inRandomOrder()->first()?->id ?? 1,
            'po_number' => 'PO-' . $this->faker->unique()->numberBetween(1000, 9999),
            'ref_no' => $this->faker->optional()->word(),
            'date_order' => $this->faker->date(),
            'date_planned' => $this->faker->optional()->date(),
            'state' => 'draft',
            'currency_id' => Currency::inRandomOrder()->first()?->id ?? 1,
            'exchange_rate' => 1.0,
            'amount_untaxed' => 0,
            'amount_tax' => 0,
            'amount_total' => 0,
            'notes' => $this->faker->sentence(),
            'created_by' => User::inRandomOrder()->first()?->id ?? 1,
        ];
    }
}
