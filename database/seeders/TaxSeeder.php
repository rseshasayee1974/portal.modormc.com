<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tax;
use App\Models\Plant;
use Illuminate\Support\Facades\DB;

class TaxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plants = Plant::all();

        foreach ($plants as $plant) {
            // Check if taxes already exist for this plant to avoid duplicates
            if (Tax::where('plant_id', $plant->id)->exists()) {
                continue;
            }

            // --- GST Slabs (Sales) ---
            $slabs = [0, 0.25, 3, 5, 12, 18, 28];

            foreach ($slabs as $rate) {
                $suffix = "";
                if ($rate == 0) {
                    // Create both Exempt and Nil Rated for 0%
                    $types = ['Exempt', 'Nil Rated'];
                    foreach ($types as $type) {
                        Tax::create([
                            'plant_id' => $plant->id,
                            'entity_id' => $plant->entity_id,
                            'tax_name' => "GST $rate% ($type) (Sales)",
                            'tax_type' => 'sales',
                            'tax_group' => 'GST',
                            'tax_rate' => $rate,
                            'parent_id' => null,
                            'status' => 1,
                        ]);
                    }
                    continue;
                }

                // Root GST (Combined)
                $gstRoot = Tax::create([
                    'plant_id' => $plant->id,
                    'entity_id' => $plant->entity_id,
                    'tax_name' => "GST $rate% (Sales)",
                    'tax_type' => 'sales',
                    'tax_group' => 'GST',
                    'tax_rate' => $rate,
                    'parent_id' => null,
                    'status' => 1,
                ]);

                // CGST (Child)
                Tax::create([
                    'plant_id' => $plant->id,
                    'entity_id' => $plant->entity_id,
                    'tax_name' => "CGST " . ($rate/2) . "%",
                    'tax_type' => 'sales',
                    'tax_group' => 'CGST',
                    'tax_rate' => $rate/2,
                    'parent_id' => $gstRoot->id,
                    'status' => 1,
                ]);

                // SGST (Child)
                Tax::create([
                    'plant_id' => $plant->id,
                    'entity_id' => $plant->entity_id,
                    'tax_name' => "SGST " . ($rate/2) . "%",
                    'tax_type' => 'sales',
                    'tax_group' => 'SGST',
                    'tax_rate' => $rate/2,
                    'parent_id' => $gstRoot->id,
                    'status' => 1,
                ]);

                // IGST (Separate Parent)
                Tax::create([
                    'plant_id' => $plant->id,
                    'entity_id' => $plant->entity_id,
                    'tax_name' => "IGST $rate%",
                    'tax_type' => 'sales',
                    'tax_group' => 'IGST',
                    'tax_rate' => $rate,
                    'parent_id' => null,
                    'status' => 1,
                ]);
            }

            // --- GST Slabs (Purchase) ---
            foreach ($slabs as $rate) {
                if ($rate == 0) {
                    $types = ['Exempt', 'Nil Rated'];
                    foreach ($types as $type) {
                        Tax::create([
                            'plant_id' => $plant->id,
                            'entity_id' => $plant->entity_id,
                            'tax_name' => "GST $rate% ($type) (Purchase)",
                            'tax_type' => 'purchase',
                            'tax_group' => 'GST',
                            'tax_rate' => $rate,
                            'parent_id' => null,
                            'status' => 1,
                        ]);
                    }
                    continue;
                }

                $rootPurchase = Tax::create([
                    'plant_id' => $plant->id,
                    'entity_id' => $plant->entity_id,
                    'tax_name' => "GST $rate% (Purchase)",
                    'tax_type' => 'purchase',
                    'tax_group' => 'GST',
                    'tax_rate' => $rate,
                    'parent_id' => null,
                    'status' => 1,
                ]);

                Tax::create([
                    'plant_id' => $plant->id,
                    'entity_id' => $plant->entity_id,
                    'tax_name' => "CGST " . ($rate/2) . "% (Purchase)",
                    'tax_type' => 'purchase',
                    'tax_group' => 'CGST',
                    'tax_rate' => $rate/2,
                    'parent_id' => $rootPurchase->id,
                    'status' => 1,
                ]);

                Tax::create([
                    'plant_id' => $plant->id,
                    'entity_id' => $plant->entity_id,
                    'tax_name' => "SGST " . ($rate/2) . "% (Purchase)",
                    'tax_type' => 'purchase',
                    'tax_group' => 'SGST',
                    'tax_rate' => $rate/2,
                    'parent_id' => $rootPurchase->id,
                    'status' => 1,
                ]);

                // IGST Purchase (Separate Parent)
                Tax::create([
                    'plant_id' => $plant->id,
                    'entity_id' => $plant->entity_id,
                    'tax_name' => "IGST $rate% (Purchase)",
                    'tax_type' => 'purchase',
                    'tax_group' => 'IGST',
                    'tax_rate' => $rate,
                    'parent_id' => null,
                    'status' => 1,
                ]);
            }

            // --- TDS Slabs ---
            $tdsSlabs = [
                ['name' => 'Rent', 'rate' => 10.00],
                ['name' => 'Contractor', 'rate' => 1.00],
                ['name' => 'Professional', 'rate' => 2.00],
                ['name' => 'Purchase of Goods (194Q)', 'rate' => 0.10],
            ];

            foreach ($tdsSlabs as $tds) {
                Tax::create([
                    'plant_id' => $plant->id,
                    'entity_id' => $plant->entity_id,
                    'tax_name' => "TDS {$tds['rate']}% ({$tds['name']})",
                    'tax_type' => 'purchase',
                    'tax_group' => 'TDS',
                    'tax_rate' => $tds['rate'],
                    'parent_id' => null,
                    'status' => 1,
                ]);
            }

            // --- TCS Slabs ---
            $tcsSlabs = [
                ['name' => 'Sale of Goods (206C 1H)', 'rate' => 0.10],
                ['name' => 'Scrap', 'rate' => 1.00],
            ];

            foreach ($tcsSlabs as $tcs) {
                Tax::create([
                    'plant_id' => $plant->id,
                    'entity_id' => $plant->entity_id,
                    'tax_name' => "TCS {$tcs['rate']}% ({$tcs['name']})",
                    'tax_type' => 'sales',
                    'tax_group' => 'TCS',
                    'tax_rate' => $tcs['rate'],
                    'parent_id' => null,
                    'status' => 1,
                ]);
            }

            // --- CESS ---
            Tax::create([
                'plant_id' => $plant->id,
                'entity_id' => $plant->entity_id,
                'tax_name' => "Compensation Cess 1%",
                'tax_type' => 'sales',
                'tax_group' => 'CESS',
                'tax_rate' => 1.00,
                'parent_id' => null,
                'status' => 1,
            ]);
        }
    }
}
