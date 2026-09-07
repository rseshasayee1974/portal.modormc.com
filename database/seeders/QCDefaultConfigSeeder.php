<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\QC\QcTestType;
use App\Models\QC\QcTestParameter;
use App\Models\QC\QcUnit;
use App\Models\QC\QcMaterialTest;
use App\Models\QC\QcTestSchedule;
use App\Models\QC\QcSample;
use App\Models\QC\QcTest;
use App\Models\QC\QcTestMeasurement;
use App\Models\QC\QcTestResult;
use App\Models\Entity;
use App\Models\Plant;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QCDefaultConfigSeeder extends Seeder
{
    public function run(): void
    {
        $plants = Plant::all();
        if ($plants->isEmpty()) {
            return;
        }

        // 1. Purge all QC tables to eliminate legacy/dummy records
        Schema::disableForeignKeyConstraints();
        QcTestResult::truncate();
        QcTestMeasurement::truncate();
        QcTest::truncate();
        QcSample::truncate();
        QcTestSchedule::truncate();
        QcMaterialTest::truncate();
        if (Schema::hasTable('qc_test_rules')) {
            DB::table('qc_test_rules')->truncate();
        }
        QcTestParameter::truncate();
        QcTestType::truncate();
        QcUnit::truncate();
        Schema::enableForeignKeyConstraints();

        $defaultUser = User::first() ?: User::factory()->create();
        $userId = $defaultUser->id;

        // =========================================================================
        // DYNAMIC UNITS OF MEASUREMENT (GLOBAL QC UNITS MASTER)
        // =========================================================================
        $unitsData = [
            ['code' => 'G', 'name' => 'Gram', 'symbol' => 'g', 'dimension' => 'mass'],
            ['code' => 'KG', 'name' => 'Kilogram', 'symbol' => 'kg', 'dimension' => 'mass'],
            ['code' => 'MM', 'name' => 'Millimeter', 'symbol' => 'mm', 'dimension' => 'length'],
            ['code' => 'N_MM2', 'name' => 'Newton per Square Millimeter', 'symbol' => 'N/mm²', 'dimension' => 'pressure'],
            ['code' => 'MPA', 'name' => 'Megapascal', 'symbol' => 'MPa', 'dimension' => 'pressure'],
            ['code' => 'KN', 'name' => 'Kilonewton', 'symbol' => 'kN', 'dimension' => 'force'],
            ['code' => 'PERCENT', 'name' => 'Percentage', 'symbol' => '%', 'dimension' => 'ratio'],
            ['code' => 'CELSIUS', 'name' => 'Degree Celsius', 'symbol' => '°C', 'dimension' => 'temperature'],
            ['code' => 'KG_M3', 'name' => 'Kilogram per Cubic Meter', 'symbol' => 'kg/m³', 'dimension' => 'density'],
            ['code' => 'LITRE', 'name' => 'Litre', 'symbol' => 'Litre', 'dimension' => 'volume'],
            ['code' => 'MINUTES', 'name' => 'Minutes', 'symbol' => 'Minutes', 'dimension' => 'time'],
            ['code' => 'DAYS', 'name' => 'Days', 'symbol' => 'Days', 'dimension' => 'time'],
            ['code' => 'PPM', 'name' => 'Parts Per Million', 'symbol' => 'ppm', 'dimension' => 'ratio'],
            ['code' => 'G_CC', 'name' => 'Gram per Cubic Centimeter', 'symbol' => 'g/cc', 'dimension' => 'density'],
        ];

        foreach ($unitsData as $u) {
            QcUnit::updateOrCreate(
                ['code' => $u['code']],
                [
                    'plant_id' => null,
                    'name' => $u['name'],
                    'symbol' => $u['symbol'],
                    'dimension' => $u['dimension'],
                    'is_active' => true,
                    'created_by' => $userId,
                ]
            );
        }

        foreach ($plants as $plant) {
            $plantId = $plant->id;
            $companyId = $plant->entity_id;

            // =========================================================================
            // 1. CONCRETE COMPRESSIVE STRENGTH (7-DAY) (IS 516 / IS 456) - MULTI_TRIAL
            // =========================================================================
            $cube7DTest = QcTestType::create([
                'plant_id' => $plantId,
                'code' => 'CUBE_STRENGTH_7D',
                'name' => 'Concrete Cube Compressive Strength (7 Days)',
                'category' => 'Concrete',
                'material_type' => 'Hardened Concrete',
                'standard_reference' => 'IS 516 / IS 456 (7-Day Target)',
                'calculation_type' => 'formula',
                'layout_type' => 'MULTI_TRIAL',
                'description' => 'Compressive strength of standard 150mm cubes tested after 7 days water curing (Target: 67-70% of characteristic strength)',
                'is_active' => true,
                'created_by' => $userId,
            ]);

            $pAge7 = QcTestParameter::create([
                'test_type_id' => $cube7DTest->id,
                'code' => 'SPECIMEN_AGE',
                'name' => 'Specimen Curing Age',
                'data_type' => 'integer',
                'unit' => 'Days',
                'default_value' => '7',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 1,
                'created_by' => $userId,
            ]);
            $pMass7 = QcTestParameter::create([
                'test_type_id' => $cube7DTest->id,
                'code' => 'MASS_KG',
                'name' => 'Specimen Mass',
                'data_type' => 'decimal',
                'unit' => 'kg',
                'is_required' => false,
                'is_calculated' => false,
                'display_order' => 2,
                'created_by' => $userId,
            ]);
            $pLoad7 = QcTestParameter::create([
                'test_type_id' => $cube7DTest->id,
                'code' => 'LOAD_KN',
                'name' => 'Crushing Failure Load',
                'data_type' => 'decimal',
                'unit' => 'kN',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 3,
                'created_by' => $userId,
            ]);
            $pCubeDens7 = QcTestParameter::create([
                'test_type_id' => $cube7DTest->id,
                'code' => 'DENSITY_KGM3',
                'name' => 'Hardened Cube Density',
                'data_type' => 'decimal',
                'unit' => 'kg/m³',
                'is_required' => false,
                'is_calculated' => true,
                'formula' => 'MASS_KG / 0.003375',
                'display_order' => 4,
                'created_by' => $userId,
            ]);
            $pCubeStr7 = QcTestParameter::create([
                'test_type_id' => $cube7DTest->id,
                'code' => 'COMP_STRENGTH',
                'name' => '7-Day Compressive Strength',
                'data_type' => 'decimal',
                'unit' => 'N/mm²',
                'is_required' => true,
                'is_calculated' => true,
                'formula' => 'LOAD_KN / 22.5',
                'display_order' => 5,
                'rule_type' => 'GREATER_THAN_OR_EQUAL',
                'min_value' => 20.00,
                'standard_reference' => 'IS 456 (Min 67% of M30 = 20.0 N/mm²)',
                'created_by' => $userId,
            ]);

            // =========================================================================
            // 2. CONCRETE COMPRESSIVE STRENGTH (28-DAY) (IS 516 / IS 456) - MULTI_TRIAL
            // =========================================================================
            $cube28DTest = QcTestType::create([
                'plant_id' => $plantId,
                'code' => 'CUBE_STRENGTH_28D',
                'name' => 'Concrete Cube Compressive Strength (28 Days)',
                'category' => 'Concrete',
                'material_type' => 'Hardened Concrete',
                'standard_reference' => 'IS 516 / IS 456 (28-Day Target)',
                'calculation_type' => 'formula',
                'layout_type' => 'MULTI_TRIAL',
                'description' => 'Compressive strength of standard 150mm cubes tested after 28 days water curing (Target: 100% of characteristic strength)',
                'is_active' => true,
                'created_by' => $userId,
            ]);

            $pAge28 = QcTestParameter::create([
                'test_type_id' => $cube28DTest->id,
                'code' => 'SPECIMEN_AGE',
                'name' => 'Specimen Curing Age',
                'data_type' => 'integer',
                'unit' => 'Days',
                'default_value' => '28',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 1,
                'created_by' => $userId,
            ]);
            $pMass28 = QcTestParameter::create([
                'test_type_id' => $cube28DTest->id,
                'code' => 'MASS_KG',
                'name' => 'Specimen Mass',
                'data_type' => 'decimal',
                'unit' => 'kg',
                'is_required' => false,
                'is_calculated' => false,
                'display_order' => 2,
                'created_by' => $userId,
            ]);
            $pLoad28 = QcTestParameter::create([
                'test_type_id' => $cube28DTest->id,
                'code' => 'LOAD_KN',
                'name' => 'Crushing Failure Load',
                'data_type' => 'decimal',
                'unit' => 'kN',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 3,
                'created_by' => $userId,
            ]);
            $pCubeDens28 = QcTestParameter::create([
                'test_type_id' => $cube28DTest->id,
                'code' => 'DENSITY_KGM3',
                'name' => 'Hardened Cube Density',
                'data_type' => 'decimal',
                'unit' => 'kg/m³',
                'is_required' => false,
                'is_calculated' => true,
                'formula' => 'MASS_KG / 0.003375',
                'display_order' => 4,
                'rule_type' => 'GREATER_THAN_OR_EQUAL',
                'min_value' => 2350.00,
                'standard_reference' => 'IS 456 Concrete Density Norms',
                'created_by' => $userId,
            ]);
            $pCubeStr28 = QcTestParameter::create([
                'test_type_id' => $cube28DTest->id,
                'code' => 'COMP_STRENGTH',
                'name' => '28-Day Compressive Strength',
                'data_type' => 'decimal',
                'unit' => 'N/mm²',
                'is_required' => true,
                'is_calculated' => true,
                'formula' => 'LOAD_KN / 22.5',
                'display_order' => 5,
                'rule_type' => 'GREATER_THAN_OR_EQUAL',
                'min_value' => 30.00,
                'standard_reference' => 'IS 456 Characteristic Compressive Strength (fck)',
                'created_by' => $userId,
            ]);

            // =========================================================================
            // 3. CONCRETE FLEXURAL STRENGTH (IS 516) - MULTI_TRIAL
            // =========================================================================
            $flexTest = QcTestType::create([
                'plant_id' => $plantId,
                'code' => 'FLEXURAL_STRENGTH',
                'name' => 'Concrete Flexural Strength (Beam Test)',
                'category' => 'Concrete',
                'material_type' => 'Hardened Concrete',
                'standard_reference' => 'IS 516 / IS 456',
                'calculation_type' => 'formula',
                'layout_type' => 'MULTI_TRIAL',
                'description' => 'Flexural modulus of rupture on 150mm x 150mm x 700mm concrete beams',
                'is_active' => true,
                'created_by' => $userId,
            ]);

            $pSpan = QcTestParameter::create([
                'test_type_id' => $flexTest->id,
                'code' => 'SPAN_MM',
                'name' => 'Support Span Length (L)',
                'data_type' => 'decimal',
                'unit' => 'mm',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 1,
                'created_by' => $userId,
            ]);
            $pWidth = QcTestParameter::create([
                'test_type_id' => $flexTest->id,
                'code' => 'WIDTH_MM',
                'name' => 'Beam Width (b)',
                'data_type' => 'decimal',
                'unit' => 'mm',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 2,
                'created_by' => $userId,
            ]);
            $pDepth = QcTestParameter::create([
                'test_type_id' => $flexTest->id,
                'code' => 'DEPTH_MM',
                'name' => 'Beam Depth (d)',
                'data_type' => 'decimal',
                'unit' => 'mm',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 3,
                'created_by' => $userId,
            ]);
            $pFlexLoad = QcTestParameter::create([
                'test_type_id' => $flexTest->id,
                'code' => 'LOAD_KN',
                'name' => 'Breaking Load (P)',
                'data_type' => 'decimal',
                'unit' => 'kN',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 4,
                'created_by' => $userId,
            ]);
            $pFlexStr = QcTestParameter::create([
                'test_type_id' => $flexTest->id,
                'code' => 'FLEX_STRENGTH',
                'name' => 'Modulus of Rupture (fb)',
                'data_type' => 'decimal',
                'unit' => 'N/mm²',
                'is_required' => true,
                'is_calculated' => true,
                'formula' => '(LOAD_KN * 1000 * SPAN_MM) / (WIDTH_MM * DEPTH_MM * DEPTH_MM)',
                'display_order' => 5,
                'rule_type' => 'GREATER_THAN_OR_EQUAL',
                'min_value' => 3.80,
                'standard_reference' => 'IS 456 Cl 6.2.2 (fcr = 0.7 * sqrt(fck))',
                'created_by' => $userId,
            ]);

            // =========================================================================
            // 4. FRESH CONCRETE SLUMP & WORKABILITY (IS 1199) - SINGLE_TRIAL
            // =========================================================================
            $slumpTest = QcTestType::create([
                'plant_id' => $plantId,
                'code' => 'SLUMP',
                'name' => 'Fresh Concrete Slump & Workability',
                'category' => 'Concrete',
                'material_type' => 'Fresh Concrete',
                'standard_reference' => 'IS 1199 (Part 2)',
                'calculation_type' => 'manual',
                'layout_type' => 'SINGLE_TRIAL',
                'description' => 'Fresh concrete workability, consistency and temperature at plant & site',
                'is_active' => true,
                'created_by' => $userId,
            ]);

            $pInitSlump = QcTestParameter::create([
                'test_type_id' => $slumpTest->id,
                'code' => 'INITIAL_SLUMP',
                'name' => 'Initial Slump at Batching',
                'data_type' => 'decimal',
                'unit' => 'mm',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 1,
                'rule_type' => 'RANGE',
                'min_value' => 100.00,
                'max_value' => 150.00,
                'standard_reference' => 'IS 456 / IS 1199 Pumpable Concrete Range',
                'created_by' => $userId,
            ]);
            $pSiteSlump = QcTestParameter::create([
                'test_type_id' => $slumpTest->id,
                'code' => 'SITE_SLUMP',
                'name' => 'Slump at Site Discharge',
                'data_type' => 'decimal',
                'unit' => 'mm',
                'is_required' => false,
                'is_calculated' => false,
                'display_order' => 2,
                'created_by' => $userId,
            ]);
            $pConcTemp = QcTestParameter::create([
                'test_type_id' => $slumpTest->id,
                'code' => 'CONC_TEMP',
                'name' => 'Concrete Temperature',
                'data_type' => 'decimal',
                'unit' => '°C',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 3,
                'rule_type' => 'LESS_THAN_OR_EQUAL',
                'max_value' => 32.00,
                'standard_reference' => 'IS 456 Hot Weather Concreting Limit',
                'created_by' => $userId,
            ]);

            // =========================================================================
            // 5. 20MM COARSE AGGREGATE SIEVE ANALYSIS - SIEVE_GRADATION
            // =========================================================================
            $sieveCoarse = QcTestType::create([
                'plant_id' => $plantId,
                'code' => 'SIEVE_COARSE',
                'name' => 'Coarse Aggregate Sieve Analysis (20mm Graded)',
                'category' => 'Aggregate',
                'material_type' => 'Coarse Aggregate',
                'standard_reference' => 'IS 2386 (Part 1) / IS 383:2016',
                'calculation_type' => 'formula',
                'layout_type' => 'SIEVE_GRADATION',
                'grid_config' => [
                    'sieves' => [
                        ['label' => '40.00 mm', 'is_limit' => '100'],
                        ['label' => '20.00 mm', 'is_limit' => '85-100'],
                        ['label' => '10.00 mm', 'is_limit' => '0-20'],
                        ['label' => '4.75 mm', 'is_limit' => '0-5'],
                        ['label' => 'Pan', 'is_limit' => '-'],
                    ]
                ],
                'description' => 'Particle size gradation compliance for 20mm graded coarse aggregate',
                'is_active' => true,
                'created_by' => $userId,
            ]);

            $pTotWt = QcTestParameter::create([
                'test_type_id' => $sieveCoarse->id,
                'code' => 'TOTAL_SAMPLE_WT',
                'name' => 'Total Dry Sample Weight',
                'data_type' => 'decimal',
                'unit' => 'g',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 1,
                'created_by' => $userId,
            ]);
            $pFm = QcTestParameter::create([
                'test_type_id' => $sieveCoarse->id,
                'code' => 'FINENESS_MODULUS',
                'name' => 'Fineness Modulus (FM)',
                'data_type' => 'decimal',
                'unit' => '',
                'is_required' => true,
                'is_calculated' => true,
                'display_order' => 2,
                'rule_type' => 'RANGE',
                'min_value' => 6.50,
                'max_value' => 7.80,
                'standard_reference' => 'IS 383 Table 2 Graded 20mm',
                'created_by' => $userId,
            ]);

            // =========================================================================
            // 6. FINE AGGREGATE (M-SAND) SIEVE ANALYSIS - SIEVE_GRADATION
            // =========================================================================
            $sieveSand = QcTestType::create([
                'plant_id' => $plantId,
                'code' => 'SIEVE_SAND',
                'name' => 'Fine Aggregate (M-Sand) Gradation & FM',
                'category' => 'Aggregate',
                'material_type' => 'Fine Aggregate',
                'standard_reference' => 'IS 2386 (Part 1) / IS 383:2016',
                'calculation_type' => 'formula',
                'layout_type' => 'SIEVE_GRADATION',
                'grid_config' => [
                    'sieves' => [
                        ['label' => '10.00 mm', 'is_limit' => '100'],
                        ['label' => '4.75 mm', 'is_limit' => '90-100'],
                        ['label' => '2.36 mm', 'is_limit' => '60-95'],
                        ['label' => '1.18 mm', 'is_limit' => '30-70'],
                        ['label' => '600 micron', 'is_limit' => '15-34'],
                        ['label' => '300 micron', 'is_limit' => '5-20'],
                        ['label' => '150 micron', 'is_limit' => '0-10'],
                        ['label' => 'Pan', 'is_limit' => '-'],
                    ]
                ],
                'description' => 'Gradation curve, silt content and Zone II classification for M-Sand',
                'is_active' => true,
                'created_by' => $userId,
            ]);

            $pSandTot = QcTestParameter::create([
                'test_type_id' => $sieveSand->id,
                'code' => 'TOTAL_SAMPLE_WT',
                'name' => 'Total Dry Sample Weight',
                'data_type' => 'decimal',
                'unit' => 'g',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 1,
                'created_by' => $userId,
            ]);
            $pSandFm = QcTestParameter::create([
                'test_type_id' => $sieveSand->id,
                'code' => 'FINENESS_MODULUS',
                'name' => 'Fineness Modulus (FM)',
                'data_type' => 'decimal',
                'unit' => '',
                'is_required' => true,
                'is_calculated' => true,
                'display_order' => 2,
                'rule_type' => 'RANGE',
                'min_value' => 2.20,
                'max_value' => 3.20,
                'standard_reference' => 'IS 383:2016 Zone II Specification',
                'created_by' => $userId,
            ]);
            $pSilt = QcTestParameter::create([
                'test_type_id' => $sieveSand->id,
                'code' => 'SILT_CONTENT_PCT',
                'name' => 'Micro-fines / Silt (< 75µm)',
                'data_type' => 'decimal',
                'unit' => '%',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 3,
                'rule_type' => 'LESS_THAN_OR_EQUAL',
                'max_value' => 7.00,
                'standard_reference' => 'IS 383 Max 7% for Crushed Sand',
                'created_by' => $userId,
            ]);

            // =========================================================================
            // 7. COMBINED FLAKINESS & ELONGATION INDEX - GAUGE_MATRIX
            // =========================================================================
            $flakElongTest = QcTestType::create([
                'plant_id' => $plantId,
                'code' => 'FLAKINESS_ELONGATION',
                'name' => 'Combined Flakiness & Elongation Index',
                'category' => 'Aggregate',
                'material_type' => 'Coarse Aggregate',
                'standard_reference' => 'IS 2386 (Part 1) / IS 383:2016',
                'calculation_type' => 'formula',
                'layout_type' => 'GAUGE_MATRIX',
                'grid_config' => [
                    'fractions' => [
                        ['label' => '25-20 mm', 'thickness_mm' => 13.50, 'length_mm' => 40.50],
                        ['label' => '20-16 mm', 'thickness_mm' => 10.80, 'length_mm' => 32.40],
                        ['label' => '16-12.5 mm', 'thickness_mm' => 8.55, 'length_mm' => 25.60],
                        ['label' => '12.5-10 mm', 'thickness_mm' => 6.75, 'length_mm' => 20.20],
                        ['label' => '10-6.3 mm', 'thickness_mm' => 4.89, 'length_mm' => 14.70],
                    ]
                ],
                'description' => 'Particle shape index on coarse aggregates using thickness & length gauges',
                'is_active' => true,
                'created_by' => $userId,
            ]);

            $pFlakIdx = QcTestParameter::create([
                'test_type_id' => $flakElongTest->id,
                'code' => 'FLAKINESS_INDEX_PCT',
                'name' => 'Flakiness Index (%)',
                'data_type' => 'decimal',
                'unit' => '%',
                'is_required' => true,
                'is_calculated' => true,
                'display_order' => 1,
                'rule_type' => 'LESS_THAN_OR_EQUAL',
                'max_value' => 25.00,
                'standard_reference' => 'IS 383 Individual Shape Limit (Max 25%)',
                'created_by' => $userId,
            ]);
            $pElongIdx = QcTestParameter::create([
                'test_type_id' => $flakElongTest->id,
                'code' => 'ELONGATION_INDEX_PCT',
                'name' => 'Elongation Index (%)',
                'data_type' => 'decimal',
                'unit' => '%',
                'is_required' => true,
                'is_calculated' => true,
                'display_order' => 2,
                'created_by' => $userId,
            ]);
            $pCombIdx = QcTestParameter::create([
                'test_type_id' => $flakElongTest->id,
                'code' => 'COMBINED_INDEX_PCT',
                'name' => 'Combined Flakiness + Elongation',
                'data_type' => 'decimal',
                'unit' => '%',
                'is_required' => true,
                'is_calculated' => true,
                'formula' => 'FLAKINESS_INDEX_PCT + ELONGATION_INDEX_PCT',
                'display_order' => 3,
                'rule_type' => 'LESS_THAN_OR_EQUAL',
                'max_value' => 40.00,
                'standard_reference' => 'IS 383 Cl 5.3 Combined Shape Limit (Max 40%)',
                'created_by' => $userId,
            ]);

            // =========================================================================
            // 8. AGGREGATE IMPACT VALUE (AIV) - SINGLE_TRIAL
            // =========================================================================
            $aivTest = QcTestType::create([
                'plant_id' => $plantId,
                'code' => 'AIV',
                'name' => 'Aggregate Impact Value (AIV)',
                'category' => 'Aggregate',
                'material_type' => 'Coarse Aggregate',
                'standard_reference' => 'IS 2386 (Part 4) / IS 383',
                'calculation_type' => 'formula',
                'layout_type' => 'SINGLE_TRIAL',
                'description' => 'Resistance of aggregate to sudden shock load in impact testing machine',
                'is_active' => true,
                'created_by' => $userId,
            ]);

            $pAivA = QcTestParameter::create([
                'test_type_id' => $aivTest->id,
                'code' => 'MASS_MOULD_A',
                'name' => 'Initial Dry Sample Weight (A)',
                'data_type' => 'decimal',
                'unit' => 'g',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 1,
                'created_by' => $userId,
            ]);
            $pAivB = QcTestParameter::create([
                'test_type_id' => $aivTest->id,
                'code' => 'MASS_PASSING_B',
                'name' => 'Mass Passing 2.36mm Sieve (B)',
                'data_type' => 'decimal',
                'unit' => 'g',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 2,
                'created_by' => $userId,
            ]);
            $pAivVal = QcTestParameter::create([
                'test_type_id' => $aivTest->id,
                'code' => 'AIV_PCT',
                'name' => 'Aggregate Impact Value (%)',
                'data_type' => 'decimal',
                'unit' => '%',
                'is_required' => true,
                'is_calculated' => true,
                'formula' => 'MASS_PASSING_B / MASS_MOULD_A * 100',
                'display_order' => 3,
                'rule_type' => 'LESS_THAN_OR_EQUAL',
                'max_value' => 30.00,
                'standard_reference' => 'IS 383 (Max 30% for concrete wear surfaces)',
                'created_by' => $userId,
            ]);

            // =========================================================================
            // 9. AGGREGATE SURFACE MOISTURE CONTENT - BEFORE_AFTER
            // =========================================================================
            $moistureTest = QcTestType::create([
                'plant_id' => $plantId,
                'code' => 'MOISTURE',
                'name' => 'Aggregate Surface Moisture Content',
                'category' => 'Aggregate',
                'material_type' => 'Aggregates',
                'standard_reference' => 'IS 2386 (Part 3)',
                'calculation_type' => 'formula',
                'layout_type' => 'BEFORE_AFTER',
                'description' => 'Determination of aggregate moisture for plant water-cement ratio correction',
                'is_active' => true,
                'created_by' => $userId,
            ]);

            $pWet = QcTestParameter::create([
                'test_type_id' => $moistureTest->id,
                'code' => 'WET_WEIGHT',
                'name' => 'Weight of Moist / Wet Sample (W1)',
                'data_type' => 'decimal',
                'unit' => 'g',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 1,
                'created_by' => $userId,
            ]);
            $pDry = QcTestParameter::create([
                'test_type_id' => $moistureTest->id,
                'code' => 'DRY_WEIGHT',
                'name' => 'Weight of Oven-Dry Sample (W2)',
                'data_type' => 'decimal',
                'unit' => 'g',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 2,
                'created_by' => $userId,
            ]);
            $pMoistPct = QcTestParameter::create([
                'test_type_id' => $moistureTest->id,
                'code' => 'MOISTURE_PCT',
                'name' => 'Moisture Content (%)',
                'data_type' => 'decimal',
                'unit' => '%',
                'is_required' => true,
                'is_calculated' => true,
                'formula' => '(WET_WEIGHT - DRY_WEIGHT) / DRY_WEIGHT * 100',
                'display_order' => 3,
                'rule_type' => 'RANGE',
                'min_value' => 0.00,
                'max_value' => 6.00,
                'standard_reference' => 'Daily Batching Plant Quality Control Tolerance',
                'created_by' => $userId,
            ]);

            // =========================================================================
            // 10. CEMENT SETTING TIMES & CONSISTENCY - TIMED_OBSERVATION
            // =========================================================================
            $cementTest = QcTestType::create([
                'plant_id' => $plantId,
                'code' => 'CEMENT_SETTING',
                'name' => 'Cement Setting Times & Consistency',
                'category' => 'Cement',
                'material_type' => 'OPC / PPC Cement',
                'standard_reference' => 'IS 4031 (Part 4 & 5) / IS 269:2015',
                'calculation_type' => 'manual',
                'layout_type' => 'TIMED_OBSERVATION',
                'description' => 'Initial and final setting times of standard cement paste using Vicat apparatus',
                'is_active' => true,
                'created_by' => $userId,
            ]);

            $pConsist = QcTestParameter::create([
                'test_type_id' => $cementTest->id,
                'code' => 'NORMAL_CONSISTENCY',
                'name' => 'Standard Normal Consistency (P)',
                'data_type' => 'decimal',
                'unit' => '%',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 1,
                'created_by' => $userId,
            ]);
            $pIst = QcTestParameter::create([
                'test_type_id' => $cementTest->id,
                'code' => 'INITIAL_SETTING_TIME',
                'name' => 'Initial Setting Time (IST)',
                'data_type' => 'integer',
                'unit' => 'Minutes',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 2,
                'rule_type' => 'GREATER_THAN_OR_EQUAL',
                'min_value' => 30.00,
                'standard_reference' => 'IS 269:2015 (Min 30 mins)',
                'created_by' => $userId,
            ]);
            $pFst = QcTestParameter::create([
                'test_type_id' => $cementTest->id,
                'code' => 'FINAL_SETTING_TIME',
                'name' => 'Final Setting Time (FST)',
                'data_type' => 'integer',
                'unit' => 'Minutes',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 3,
                'rule_type' => 'LESS_THAN_OR_EQUAL',
                'max_value' => 600.00,
                'standard_reference' => 'IS 269:2015 (Max 600 mins)',
                'created_by' => $userId,
            ]);

            // =========================================================================
            // 11. WATER CHEMICAL PURITY & PH - SINGLE_TRIAL
            // =========================================================================
            $waterTest = QcTestType::create([
                'plant_id' => $plantId,
                'code' => 'WATER_TEST',
                'name' => 'Mixing Water Chemical Purity & pH Testing',
                'category' => 'Water',
                'material_type' => 'Batching Water',
                'standard_reference' => 'IS 3025 / IS 456 Cl 5.4',
                'calculation_type' => 'manual',
                'layout_type' => 'SINGLE_TRIAL',
                'description' => 'Chemical suitability, pH, chlorides, sulphates, and suspended solids in concrete mixing water',
                'is_active' => true,
                'created_by' => $userId,
            ]);

            $pPh = QcTestParameter::create([
                'test_type_id' => $waterTest->id,
                'code' => 'PH_VALUE',
                'name' => 'pH Level',
                'data_type' => 'decimal',
                'unit' => '',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 1,
                'rule_type' => 'GREATER_THAN_OR_EQUAL',
                'min_value' => 6.00,
                'standard_reference' => 'IS 456 Cl 5.4.3.2 (Min pH 6.0)',
                'created_by' => $userId,
            ]);
            $pChlor = QcTestParameter::create([
                'test_type_id' => $waterTest->id,
                'code' => 'CHLORIDES_PPM',
                'name' => 'Chlorides (Cl)',
                'data_type' => 'decimal',
                'unit' => 'ppm',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 2,
                'rule_type' => 'LESS_THAN_OR_EQUAL',
                'max_value' => 500.00,
                'standard_reference' => 'IS 456 Table 1 (Max 500 ppm for RCC)',
                'created_by' => $userId,
            ]);
            $pSulph = QcTestParameter::create([
                'test_type_id' => $waterTest->id,
                'code' => 'SULPHATES_PPM',
                'name' => 'Sulphates (SO3)',
                'data_type' => 'decimal',
                'unit' => 'ppm',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 3,
                'created_by' => $userId,
            ]);
            $pSusp = QcTestParameter::create([
                'test_type_id' => $waterTest->id,
                'code' => 'SUSPENDED_SOLIDS_PPM',
                'name' => 'Suspended Matter',
                'data_type' => 'decimal',
                'unit' => 'ppm',
                'is_required' => true,
                'is_calculated' => false,
                'display_order' => 4,
                'created_by' => $userId,
            ]);

            // =========================================================================
            // PRODUCTS & MATERIAL MAPPINGS
            // =========================================================================
            $prodCA20 = Product::firstOrCreate(
                ['plant_id' => $plantId, 'title' => '20mm Graded Blue Metal Coarse Aggregate'],
                [
                    'entity_id' => $companyId,
                    'code' => 'CA-20MM',
                    'material_code' => 'CA-20MM',
                    'status' => true,
                    'created_by' => $userId,
                ]
            );
            $prodSand = Product::firstOrCreate(
                ['plant_id' => $plantId, 'title' => 'Manufactured Sand (M-Sand Zone II)'],
                [
                    'entity_id' => $companyId,
                    'code' => 'FA-MSAND',
                    'material_code' => 'FA-MSAND',
                    'status' => true,
                    'created_by' => $userId,
                ]
            );
            $prodM30 = Product::firstOrCreate(
                ['plant_id' => $plantId, 'title' => 'M30 Grade Design Mix Concrete (Pumpable)'],
                [
                    'entity_id' => $companyId,
                    'code' => 'RMC-M30-PUMP',
                    'material_code' => 'RMC-M30-PUMP',
                    'status' => true,
                    'created_by' => $userId,
                ]
            );
            $prodCem = Product::firstOrCreate(
                ['plant_id' => $plantId, 'title' => 'OPC 53 Grade Ultratech / Chettinad Cement'],
                [
                    'entity_id' => $companyId,
                    'code' => 'CEM-OPC53',
                    'material_code' => 'CEM-OPC53',
                    'status' => true,
                    'created_by' => $userId,
                ]
            );
            $prodWater = Product::firstOrCreate(
                ['plant_id' => $plantId, 'title' => 'Borewell Batching Water Stream 1'],
                [
                    'entity_id' => $companyId,
                    'code' => 'WATER-RO-1',
                    'material_code' => 'WATER-RO-1',
                    'status' => true,
                    'created_by' => $userId,
                ]
            );

            $allTestTypes = QcTestType::where('plant_id', $plantId)->get();
            $productsList = [$prodCA20, $prodSand, $prodM30, $prodCem, $prodWater];

            foreach ($productsList as $p) {
                foreach ($allTestTypes as $tType) {
                    QcMaterialTest::firstOrCreate(
                        [
                            'plant_id' => $plantId,
                            'material_id' => $p->id,
                            'test_type_id' => $tType->id,
                        ],
                        [
                            'is_required' => true,
                            'is_active' => true,
                            'created_by' => $userId,
                        ]
                    );
                }
            }

            // =========================================================================
            // SEED SAMPLES & EXECUTIONS (Completed + Pending Tests for Testing)
            // =========================================================================
            $now = Carbon::now();

            // Sample 1: Fresh M30 Concrete Pour Batch
            $smpConc = QcSample::create([
                'plant_id' => $plantId,
                'sample_no' => 'SMP-' . $now->format('Ymd') . '-001',
                'sample_date' => $now->copy()->subHours(4),
                'material_id' => $prodM30->id,
                'source_location' => 'Batching Plant Pan Mixer 1 - Batch #5104',
                'sample_quantity' => '6 Cubes (150mm) + 40L Fresh Concrete',
                'sampled_by' => $userId,
                'status' => 'completed',
                'remarks' => 'High-Rise Foundation Raft Pour (Tower A - South Grid)',
                'created_by' => $userId,
            ]);

            // Sample 2: Inward Coarse Aggregate 20mm
            $smpAgg = QcSample::create([
                'plant_id' => $plantId,
                'sample_no' => 'SMP-' . $now->format('Ymd') . '-002',
                'sample_date' => $now->copy()->subHours(2),
                'material_id' => $prodCA20->id,
                'source_location' => 'Granite Quarry Stockpile 2 (Truck KA-04-MA-9210)',
                'sample_quantity' => '50 kg Composite Sample',
                'sampled_by' => $userId,
                'status' => 'testing',
                'remarks' => 'Inward raw material compliance testing per IS 383:2016',
                'created_by' => $userId,
            ]);

            // Sample 3: Cement Consignment OPC 53
            $smpCem = QcSample::create([
                'plant_id' => $plantId,
                'sample_no' => 'SMP-' . $now->format('Ymd') . '-003',
                'sample_date' => $now->copy()->subHours(1),
                'material_id' => $prodCem->id,
                'source_location' => 'Silo 2 - Inflow Bulk Bulker (Rake #82)',
                'sample_quantity' => '10 kg Airtight Sample',
                'sampled_by' => $userId,
                'status' => 'testing',
                'remarks' => 'Bulk cement rake physical & setting time verification',
                'created_by' => $userId,
            ]);

            // Sample 4: Batching Plant Water
            $smpWater = QcSample::create([
                'plant_id' => $plantId,
                'sample_no' => 'SMP-' . $now->format('Ymd') . '-004',
                'sample_date' => $now->copy()->subMinutes(30),
                'material_id' => $prodWater->id,
                'source_location' => 'RO Storage Tank 1 (Underground Sump)',
                'sample_quantity' => '5 Litre Polyethylene Container',
                'sampled_by' => $userId,
                'status' => 'testing',
                'remarks' => 'Monthly chemical purity test per IS 456 Table 1',
                'created_by' => $userId,
            ]);

            // =========================================================================
            // COMPLETED CUBE TESTS (7-DAY & 28-DAY WITH 3-TRIAL MEASUREMENT MATRICES)
            // =========================================================================

            // 1. Concrete Compressive Strength 7D (EXECUTED & PASSED)
            $tstCube7 = QcTest::create([
                'plant_id' => $plantId,
                'sample_id' => $smpConc->id,
                'test_type_id' => $cube7DTest->id,
                'test_no' => 'TST-' . $now->format('Ymd') . '-001-7D',
                'test_date' => $now->copy()->subHours(3),
                'tested_by' => $userId,
                'overall_status' => 'pass',
                'evaluated_at' => $now->copy()->subHours(3),
                'reviewed_by' => $userId,
                'reviewed_at' => $now->copy()->subHours(2),
                'approval_status' => 'approved',
                'remarks' => 'Standard 150mm cubes tested after 7 days water curing. Average 7-day strength is 23.26 N/mm2 (> 20.0 N/mm2 requirement for M30 concrete).',
                'created_by' => $userId,
            ]);

            // Cube 1 (7D): 520 kN -> 23.11 N/mm2
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pAge7->id, 'row_index' => 0, 'value_numeric' => 7, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pMass7->id, 'row_index' => 0, 'value_numeric' => 8.18, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pLoad7->id, 'row_index' => 0, 'value_numeric' => 520, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pCubeDens7->id, 'row_index' => 0, 'value_numeric' => 2423.70, 'is_calculated' => true]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pCubeStr7->id, 'row_index' => 0, 'value_numeric' => 23.11, 'is_calculated' => true]);

            // Cube 2 (7D): 535 kN -> 23.78 N/mm2
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pAge7->id, 'row_index' => 1, 'value_numeric' => 7, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pMass7->id, 'row_index' => 1, 'value_numeric' => 8.22, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pLoad7->id, 'row_index' => 1, 'value_numeric' => 535, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pCubeDens7->id, 'row_index' => 1, 'value_numeric' => 2435.56, 'is_calculated' => true]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pCubeStr7->id, 'row_index' => 1, 'value_numeric' => 23.78, 'is_calculated' => true]);

            // Cube 3 (7D): 515 kN -> 22.89 N/mm2
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pAge7->id, 'row_index' => 2, 'value_numeric' => 7, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pMass7->id, 'row_index' => 2, 'value_numeric' => 8.16, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pLoad7->id, 'row_index' => 2, 'value_numeric' => 515, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pCubeDens7->id, 'row_index' => 2, 'value_numeric' => 2417.78, 'is_calculated' => true]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube7->id, 'parameter_id' => $pCubeStr7->id, 'row_index' => 2, 'value_numeric' => 22.89, 'is_calculated' => true]);

            QcTestResult::create([
                'qc_test_id' => $tstCube7->id,
                'parameter_id' => $pCubeStr7->id,
                'final_value' => 23.26,
                'final_text' => '23.26',
                'status' => 'PASS',
                'criteria_snapshot' => [
                    'rule_type' => 'GREATER_THAN_OR_EQUAL',
                    'min_value' => 20.00,
                    'unit' => 'N/mm²',
                    'standard_reference' => 'IS 456 (7-Day Target = 67% fck)',
                ]
            ]);

            // 2. Concrete Compressive Strength 28D (EXECUTED & PASSED)
            $tstCube28 = QcTest::create([
                'plant_id' => $plantId,
                'sample_id' => $smpConc->id,
                'test_type_id' => $cube28DTest->id,
                'test_no' => 'TST-' . $now->format('Ymd') . '-001-28D',
                'test_date' => $now->copy()->subHours(3),
                'tested_by' => $userId,
                'overall_status' => 'pass',
                'evaluated_at' => $now->copy()->subHours(3),
                'reviewed_by' => $userId,
                'reviewed_at' => $now->copy()->subHours(2),
                'approval_status' => 'approved',
                'remarks' => 'Standard 150mm cube specimens tested after 28 days of water bath curing at 27±2°C. Average 28-day strength is 35.26 N/mm2 (> 30.0 N/mm2 target).',
                'created_by' => $userId,
            ]);

            // Cube 1 (28D): 780 kN -> 34.67 N/mm2
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pAge28->id, 'row_index' => 0, 'value_numeric' => 28, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pMass28->id, 'row_index' => 0, 'value_numeric' => 8.22, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pLoad28->id, 'row_index' => 0, 'value_numeric' => 780, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pCubeDens28->id, 'row_index' => 0, 'value_numeric' => 2435.56, 'is_calculated' => true]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pCubeStr28->id, 'row_index' => 0, 'value_numeric' => 34.67, 'is_calculated' => true]);

            // Cube 2 (28D): 805 kN -> 35.78 N/mm2
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pAge28->id, 'row_index' => 1, 'value_numeric' => 28, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pMass28->id, 'row_index' => 1, 'value_numeric' => 8.28, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pLoad28->id, 'row_index' => 1, 'value_numeric' => 805, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pCubeDens28->id, 'row_index' => 1, 'value_numeric' => 2453.33, 'is_calculated' => true]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pCubeStr28->id, 'row_index' => 1, 'value_numeric' => 35.78, 'is_calculated' => true]);

            // Cube 3 (28D): 795 kN -> 35.33 N/mm2
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pAge28->id, 'row_index' => 2, 'value_numeric' => 28, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pMass28->id, 'row_index' => 2, 'value_numeric' => 8.25, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pLoad28->id, 'row_index' => 2, 'value_numeric' => 795, 'is_calculated' => false]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pCubeDens28->id, 'row_index' => 2, 'value_numeric' => 2444.44, 'is_calculated' => true]);
            QcTestMeasurement::create(['qc_test_id' => $tstCube28->id, 'parameter_id' => $pCubeStr28->id, 'row_index' => 2, 'value_numeric' => 35.33, 'is_calculated' => true]);

            QcTestResult::create([
                'qc_test_id' => $tstCube28->id,
                'parameter_id' => $pCubeStr28->id,
                'final_value' => 35.26,
                'final_text' => '35.26',
                'status' => 'PASS',
                'criteria_snapshot' => [
                    'rule_type' => 'GREATER_THAN_OR_EQUAL',
                    'min_value' => 30.00,
                    'unit' => 'N/mm²',
                    'standard_reference' => 'IS 456 (28-Day Target = 30.0 N/mm²)',
                ]
            ]);
            QcTestResult::create([
                'qc_test_id' => $tstCube28->id,
                'parameter_id' => $pCubeDens28->id,
                'final_value' => 2444.44,
                'final_text' => '2444.44',
                'status' => 'PASS',
                'criteria_snapshot' => [
                    'rule_type' => 'GREATER_THAN_OR_EQUAL',
                    'min_value' => 2350.00,
                    'unit' => 'kg/m³',
                    'standard_reference' => 'IS 456 Concrete Density Norms',
                ]
            ]);

            // =========================================================================
            // PENDING CUBE TESTS FOR USER INTERACTIVE TESTING
            // =========================================================================
            // 1. Pending 7-Day Cube Strength Test
            QcTest::create([
                'plant_id' => $plantId,
                'sample_id' => $smpConc->id,
                'test_type_id' => $cube7DTest->id,
                'test_no' => 'TST-' . $now->format('Ymd') . '-001-7D-PENDING',
                'test_date' => $now,
                'tested_by' => $userId,
                'overall_status' => 'pending',
                'approval_status' => 'draft',
                'remarks' => 'Scheduled 7-day cube crushing test on 3 specimens.',
                'created_by' => $userId,
            ]);

            // 2. Pending 28-Day Cube Strength Test
            QcTest::create([
                'plant_id' => $plantId,
                'sample_id' => $smpConc->id,
                'test_type_id' => $cube28DTest->id,
                'test_no' => 'TST-' . $now->format('Ymd') . '-001-28D-PENDING',
                'test_date' => $now,
                'tested_by' => $userId,
                'overall_status' => 'pending',
                'approval_status' => 'draft',
                'remarks' => 'Scheduled 28-day characteristic strength verification on 3 specimens.',
                'created_by' => $userId,
            ]);

            // 3. Pending Sieve Analysis (20mm Coarse Aggregate)
            QcTest::create([
                'plant_id' => $plantId,
                'sample_id' => $smpAgg->id,
                'test_type_id' => $sieveCoarse->id,
                'test_no' => 'TST-' . $now->format('Ymd') . '-002-1-PENDING',
                'test_date' => $now,
                'tested_by' => $userId,
                'overall_status' => 'pending',
                'approval_status' => 'draft',
                'remarks' => 'Scheduled gradation test for incoming 20mm coarse aggregate.',
                'created_by' => $userId,
            ]);

            // 4. Pending Flakiness & Elongation Index
            QcTest::create([
                'plant_id' => $plantId,
                'sample_id' => $smpAgg->id,
                'test_type_id' => $flakElongTest->id,
                'test_no' => 'TST-' . $now->format('Ymd') . '-002-2-PENDING',
                'test_date' => $now,
                'tested_by' => $userId,
                'overall_status' => 'pending',
                'approval_status' => 'draft',
                'remarks' => 'Gauge matrix measurement for aggregate shape compliance.',
                'created_by' => $userId,
            ]);

            // 5. Pending Cement Setting Times
            QcTest::create([
                'plant_id' => $plantId,
                'sample_id' => $smpCem->id,
                'test_type_id' => $cementTest->id,
                'test_no' => 'TST-' . $now->format('Ymd') . '-003-1-PENDING',
                'test_date' => $now,
                'tested_by' => $userId,
                'overall_status' => 'pending',
                'approval_status' => 'draft',
                'remarks' => 'Vicat penetration testing on OPC 53 paste.',
                'created_by' => $userId,
            ]);

            // 6. Pending Water Chemical Purity & pH
            QcTest::create([
                'plant_id' => $plantId,
                'sample_id' => $smpWater->id,
                'test_type_id' => $waterTest->id,
                'test_no' => 'TST-' . $now->format('Ymd') . '-004-1-PENDING',
                'test_date' => $now,
                'tested_by' => $userId,
                'overall_status' => 'pending',
                'approval_status' => 'draft',
                'remarks' => 'Chemical test for mixing water per IS 456 Table 1.',
                'created_by' => $userId,
            ]);
        }
    }
}
