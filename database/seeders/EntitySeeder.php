<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\EntityType;

class EntitySeeder extends Seeder
{
    public function run(): void
    {
        $typeId = EntityType::where('type', 'Mining')->value('id') ?? 1;

        $entities = [
            [
                'entity_type' => $typeId,
                'legal_name' => 'Demo Mining Corp',
                'alias' => 'DMC',
                'email' => 'admin@dmc.com',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'entity_type' => $typeId,
                'legal_name' => 'Stone Crushers Ltd',
                'alias' => 'SCL',
                'email' => 'info@stone.com',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($entities as $entity) {
            DB::table('mm_entities')->updateOrInsert(
                ['legal_name' => $entity['legal_name']],
                $entity
            );
        }
    }
}
