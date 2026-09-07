<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\QC\QcSample;
use App\Models\QC\QcTestType;
use App\Models\QC\QcMaterialTest;
use App\Models\QC\QcTest;

class QCTestAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $samples = QcSample::all();
        foreach ($samples as $sample) {
            $mappings = QcMaterialTest::where('material_id', $sample->material_id)->get();
            if ($mappings->isEmpty()) {
                $testTypes = QcTestType::all();
                foreach ($testTypes as $t) {
                    QcMaterialTest::create([
                        'plant_id' => $sample->plant_id ?? 1,
                        'material_id' => $sample->material_id,
                        'test_type_id' => $t->id
                    ]);
                }
                $mappings = QcMaterialTest::where('material_id', $sample->material_id)->get();
            }

            $c = 1;
            foreach ($mappings as $m) {
                QcTest::firstOrCreate(
                    [
                        'sample_id' => $sample->id,
                        'test_type_id' => $m->test_type_id
                    ],
                    [
                        'plant_id' => $sample->plant_id ?? 1,
                        'test_no' => 'TST-' . date('Ymd') . '-' . str_pad($sample->id, 3, '0', STR_PAD_LEFT) . '-' . $c++,
                        'test_date' => now(),
                        'tested_by' => 1,
                        'overall_status' => 'pending',
                        'approval_status' => 'draft',
                        'created_by' => 1
                    ]
                );
            }
        }
    }
}
