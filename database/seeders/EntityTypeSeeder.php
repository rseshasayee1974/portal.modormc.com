<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EntityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['type' => 'Mining'],
            ['type' => 'Crushing'],
            ['type' => 'Trading'],
            ['type' => 'Manufacturing'],
            ['type' => 'Logistics'],
        ];

        foreach ($types as $type) {
            DB::table('mm_entity_types')->updateOrInsert(
                ['type' => $type['type']],
                $type
            );
        }
    }
}
