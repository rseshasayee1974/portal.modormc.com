<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PrintTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // General / cross-module
            [
                'name'      => 'Standard',
                'key'       => 'standard',
                'category'  => 'general',
                'is_system' => true,
                'mm_config' => json_encode(['primary_color' => '#6366f1', 'font' => 'Inter', 'description' => 'Clean, professional layout suitable for all document types.']),
            ],
            [
                'name'      => 'Elite',
                'key'       => 'elite',
                'category'  => 'general',
                'is_system' => true,
                'mm_config' => json_encode(['primary_color' => '#0f172a', 'font' => 'Outfit', 'description' => 'Premium dark-accented design for a corporate feel.']),
            ],
            [
                'name'      => 'Modern',
                'key'       => 'modern',
                'category'  => 'general',
                'is_system' => true,
                'mm_config' => json_encode(['primary_color' => '#0ea5e9', 'font' => 'Inter', 'description' => 'Contemporary style with bold section headers and clean lines.']),
            ],
            [
                'name'      => 'Compact',
                'key'       => 'compact',
                'category'  => 'general',
                'is_system' => true,
                'mm_config' => json_encode(['primary_color' => '#475569', 'font' => 'Roboto', 'description' => 'Space-efficient layout optimised for A5 / thermal prints.']),
            ],
            [
                'name'      => 'Standard Indigo',
                'key'       => 'standard_indigo',
                'category'  => 'general',
                'is_system' => true,
                'mm_config' => json_encode(['primary_color' => '#6366f1', 'font' => 'Inter', 'description' => 'Standard layout with an indigo brand accent.']),
            ],
            [
                'name'      => 'Minimalist Lite',
                'key'       => 'minimalist_lite',
                'category'  => 'general',
                'is_system' => true,
                'mm_config' => json_encode(['primary_color' => '#64748b', 'font' => 'Roboto', 'description' => 'Stripped-back, whitespace-first design for a modern feel.']),
            ],

            // Purchase-order specific
            [
                'name'      => 'Spreadsheet',
                'key'       => 'spreadsheet',
                'category'  => 'inventory',
                'is_system' => true,
                'mm_config' => json_encode(['primary_color' => '#16a34a', 'font' => 'Roboto', 'description' => 'Grid-style layout resembling a spreadsheet — ideal for POs.']),
            ],
            [
                'name'      => 'Tally Sheet',
                'key'       => 'tallysheet',
                'category'  => 'statement',
                'is_system' => true,
                'mm_config' => json_encode(['primary_color' => '#854d0e', 'font' => 'Outfit', 'description' => 'Tally-compatible format for statements and POs.']),
            ],

            // GST / Tax invoices
            [
                'name'      => 'Indian GST',
                'key'       => 'indian_gst',
                'category'  => 'gst',
                'is_system' => true,
                'mm_config' => json_encode(['primary_color' => '#ea580c', 'font' => 'Inter', 'description' => 'GST-compliant invoice format with CGST/SGST/IGST columns.']),
            ],
            [
                'name'      => 'Formal GST',
                'key'       => 'formal_gst',
                'category'  => 'invoice',
                'is_system' => true,
                'mm_config' => json_encode(['primary_color' => '#1e293b', 'font' => 'Outfit', 'description' => 'Formal, court-ready GST invoice with full regulatory fields.']),
            ],
        ];

        foreach ($templates as $template) {
            \App\Models\PrintTemplate::updateOrCreate(
                ['key' => $template['key']],
                $template
            );
        }
    }
}
