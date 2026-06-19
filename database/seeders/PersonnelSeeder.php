<?php

namespace Database\Seeders;

use App\Models\Personnel;
use App\Models\Entity;
use App\Models\Plant;
use App\Models\Patron;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PersonnelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $entities = Entity::all();
        $user = User::first();

        if ($entities->isEmpty()) return;

        foreach ($entities as $entity) {
            $plants = Plant::where('entity_id', $entity->id)->get();
            if ($plants->isEmpty()) continue;

            foreach ($plants as $plant) {
                Personnel::factory()
                    ->count(5)
                    ->create([
                        'plant_id' => $plant->id,
                        'created_by' => $user?->id ?? 1
                    ])
                    ->each(function ($p) use ($plant) {
                        // Create core contact record
                        $contactRecord = \App\Models\Contact::create([
                            'plant_id' => $plant->id,
                            'name' => trim($p->first_name . ' ' . $p->last_name),
                            'mobile' => '+91 9988776655',
                            'email' => '',
                            'contact_type_id' => 1,
                            'is_primary' => true,
                            'status' => 1,
                        ]);

                        // Add personnel contact link
                        $p->contacts()->create([
                            'contact_id' => (string) $contactRecord->id,
                            'contact_type' => 'Phone',
                            'contact_value' => '+91 9988776655',
                            'is_primary' => true,
                        ]);

                        // Link to patrons
                        $patrons = Patron::where('plant_id', $plant->id)->take(2)->pluck('id');
                        $p->patrons()->sync($patrons);
                    });
            }
        }
    }
}
