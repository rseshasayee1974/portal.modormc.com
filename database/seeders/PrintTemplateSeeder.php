<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PrintTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Standard Indigo',
                'key' => 'standard_indigo',
                'category' => 'general',
                'is_system' => true,
                'mm_config' => json_encode(['primary_color' => '#6366f1', 'font' => 'Inter'])
            ],
            [
                'name' => 'Minimalist Lite',
                'key' => 'minimalist_lite',
                'category' => 'general',
                'is_system' => true,
                'mm_config' => json_encode(['primary_color' => '#64748b', 'font' => 'Roboto'])
            ],
            [
                'name' => 'Formal GST',
                'key' => 'formal_gst',
                'category' => 'invoice',
                'is_system' => true,
                'mm_config' => json_encode(['primary_color' => '#1e293b', 'font' => 'Outfit'])
            ]
        ];

        foreach ($templates as $template) {
            \App\Models\PrintTemplate::updateOrCreate(
                ['key' => $template['key']],
                $template
            );
        }
    }
}
