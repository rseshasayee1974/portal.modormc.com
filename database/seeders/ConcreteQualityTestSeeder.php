<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConcreteQualityTest;
use App\Models\Plant;
use App\Models\Batch;
use Carbon\Carbon;

class ConcreteQualityTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plants = Plant::all();
        if ($plants->isEmpty()) {
            return;
        }

        $batches = Batch::all();
        $batchCount = $batches->count();

        // 10 realistic QC tests
        $testScenarios = [
            [
                'slump' => 120, // Pump concrete standard
                'temp' => 26.5,
                'air' => 1.8,
                'density' => 2410.0,
                'strength_7' => 17.5, // Target M25: ~70% at 7 days = 17.5 MPa
                'strength_28' => 25.8, // Target M25: 25.8 MPa (passed)
                'status' => 'passed',
                'remarks' => 'Slump is optimal. 28 days strength fully met target specification.',
            ],
            [
                'slump' => 140, // Pump concrete high workability
                'temp' => 28.2,
                'air' => 1.5,
                'density' => 2395.0,
                'strength_7' => 18.2,
                'strength_28' => 26.5,
                'status' => 'passed',
                'remarks' => 'Excellent workability and high flowability without segregation risk.',
            ],
            [
                'slump' => 95, // Direct placing concrete
                'temp' => 25.1,
                'air' => 2.1,
                'density' => 2420.0,
                'strength_7' => 21.0, // High early strength
                'strength_28' => 31.2, // Target M30: 31.2 MPa (passed)
                'status' => 'passed',
                'remarks' => 'Direct discharge, slight low slump but placement went smooth.',
            ],
            [
                'slump' => 80, // Too low slump!
                'temp' => 24.8,
                'air' => 2.5,
                'density' => 2435.0,
                'strength_7' => 15.1,
                'strength_28' => 22.4, // Did not meet Target M25
                'status' => 'failed',
                'remarks' => 'Placing was extremely difficult due to low slump. Strength fell slightly below target.',
            ],
            [
                'slump' => 165, // Too high slump!
                'temp' => 29.5, // High temp setting risk
                'air' => 1.2,
                'density' => 2350.0,
                'strength_7' => 16.0,
                'strength_28' => 24.1,
                'status' => 'passed',
                'remarks' => 'High water content observed. Increased segregation risk but satisfied minimal strength threshold.',
            ],
            [
                'slump' => 130,
                'temp' => 27.0,
                'air' => 1.9,
                'density' => 2405.0,
                'strength_7' => 28.5, // Target M40
                'strength_28' => 42.1, // Passed M40
                'status' => 'passed',
                'remarks' => 'High strength RMC test. Results exceed targeted M40 limit.',
            ],
            [
                'slump' => 110,
                'temp' => 26.0,
                'air' => 2.0,
                'density' => 2415.0,
                'strength_7' => 19.8,
                'strength_28' => 28.9,
                'status' => 'passed',
                'remarks' => 'Consistent batching, slump values align perfectly with target mix parameters.',
            ],
        ];

        $testedByOptions = ['S. Karthik (QC Eng)', 'M. Rajan (QC Inspector)', 'K. Selvam (Lab Technician)'];

        foreach ($plants as $plantIndex => $plant) {
            for ($i = 0; $i < 5; $i++) {
                $scenario = $testScenarios[($plantIndex * 3 + $i) % count($testScenarios)];
                
                $batchId = null;
                if ($batchCount > 0) {
                    $batchId = $batches[($plantIndex * 2 + $i) % $batchCount]->id;
                }

                $testDate = Carbon::now()->subDays(28 - $i);

                // Add core test & durability details to some records
                $coreTestStrength = ($i % 3 === 0) ? ($scenario['strength_28'] * 0.85) : null;
                $waterPermeability = ($i % 4 === 0) ? rand(12, 25) : null;
                $rapidChloride = ($i % 5 === 0) ? rand(1200, 1800) : null;

                ConcreteQualityTest::create([
                    'plant_id' => $plant->id,
                    'batch_id' => $batchId,
                    'test_code' => 'QC-' . $testDate->format('Y') . '-' . str_pad($plant->id . ($i + 1), 4, '0', STR_PAD_LEFT),
                    'test_date' => $testDate,
                    'tested_by' => $testedByOptions[$i % count($testedByOptions)],
                    
                    // Fresh Testing
                    'slump_value' => $scenario['slump'],
                    'fresh_temperature' => $scenario['temp'],
                    'air_content' => $scenario['air'],
                    'fresh_density' => $scenario['density'],
                    
                    // Hardened Testing
                    'cube_strength_7_days' => $scenario['strength_7'],
                    'cube_strength_28_days' => $scenario['strength_28'],
                    'core_test_strength' => $coreTestStrength,
                    'water_permeability' => $waterPermeability,
                    'rapid_chloride_permeability' => $rapidChloride,
                    
                    'status' => $scenario['status'],
                    'remarks' => $scenario['remarks'],
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);
            }
        }
    }
}
