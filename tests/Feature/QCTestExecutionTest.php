<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Plant;
use App\Models\Product;
use App\Models\QC\QcTest;
use App\Models\QC\QcSample;
use App\Models\QC\QcTestType;
use App\Models\QC\QcTestParameter;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QCTestExecutionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Plant $plant;
    private Product $material;
    private QcTestType $testType;
    private QcTestParameter $param1;
    private QcTestParameter $param2;
    private QcSample $sample;
    private QcTest $test;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        if (!\Illuminate\Support\Facades\Schema::hasTable('mm_purchase_order_inwards')) {
            \Illuminate\Support\Facades\Schema::create('mm_purchase_order_inwards', fn($t) => $t->id());
        }
        if (!\Illuminate\Support\Facades\Schema::hasTable('mm_batches')) {
            \Illuminate\Support\Facades\Schema::create('mm_batches', fn($t) => $t->id());
        }
        if (!\Illuminate\Support\Facades\Schema::hasTable('mm_dispatches')) {
            \Illuminate\Support\Facades\Schema::create('mm_dispatches', fn($t) => $t->id());
        }

        $entity = \App\Models\Entity::factory()->create();
        $this->plant = Plant::create([
            'entity_id' => $entity->id,
            'name' => 'Main Plant',
            'code' => 'PLT-01',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'default_entity_id' => $entity->id,
            'default_plant_id' => $this->plant->id,
        ]);

        session([
            'active_entity_id' => $entity->id,
            'active_plant_id' => $this->plant->id,
        ]);

        $this->material = Product::create([
            'title' => '20mm Aggregate',
            'code' => 'AGG-20',
            'status' => true,
            'plant_id' => $this->plant->id,
        ]);

        $this->testType = QcTestType::create([
            'name' => 'Concrete Cube Compressive Strength (28-Day)',
            'code' => 'CUBE_STRENGTH_28D',
            'category' => 'Concrete',
            'calculation_type' => 'formula',
            'layout_type' => 'MULTI_TRIAL',
            'standard_reference' => 'IS 516 / IS 456',
            'is_active' => true,
        ]);

        $this->param1 = QcTestParameter::create([
            'test_type_id' => $this->testType->id,
            'code' => 'LOAD_KN',
            'name' => 'Crushing Failure Load',
            'unit' => 'kN',
            'is_calculated' => false,
            'display_order' => 1,
        ]);

        $this->param2 = QcTestParameter::create([
            'test_type_id' => $this->testType->id,
            'code' => 'COMP_STRENGTH',
            'name' => '28-Day Compressive Strength',
            'unit' => 'N/mm²',
            'is_calculated' => true,
            'formula' => 'LOAD_KN / 22.5',
            'rule_type' => QcTestParameter::RULE_TYPE_GREATER_THAN_OR_EQUAL,
            'min_value' => 30.0,
            'display_order' => 2,
        ]);

        $this->sample = QcSample::create([
            'plant_id' => $this->plant->id,
            'sample_no' => 'SMP-001',
            'material_id' => $this->material->id,
            'sample_date' => now(),
            'sampled_by' => $this->user->id,
            'status' => 'testing',
            'created_by' => $this->user->id,
        ]);

        $this->test = QcTest::create([
            'plant_id' => $this->plant->id,
            'sample_id' => $this->sample->id,
            'test_type_id' => $this->testType->id,
            'test_no' => 'TST-001',
            'test_date' => now(),
            'tested_by' => $this->user->id,
            'overall_status' => 'pending',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_can_load_test_execution_form(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('quality.tests.execute', $this->test->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Quality/Tests/Execute')
            ->has('test')
            ->has('rules', 1)
            ->where('rules.0.parameter_id', $this->param2->id)
            ->where('rules.0.rule_type', 'GREATER_THAN_OR_EQUAL')
        );
    }

    public function test_can_submit_test_execution_and_evaluate_pass_fail(): void
    {
        $payload = [
            'test_date' => now()->toDateTimeString(),
            'measurements' => [
                [
                    'parameter_id' => $this->param1->id,
                    'value_numeric' => 800,
                    'row_index' => 0,
                ],
            ],
            'notes' => 'Tested on calibrated CTM machine',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('quality.tests.submit', $this->test->id), $payload);

        $response->assertRedirect(route('quality.tests.completed'));

        $this->test->refresh();
        $this->assertEquals('pass', $this->test->overall_status);
        $this->assertDatabaseHas('qc_test_results', [
            'qc_test_id' => $this->test->id,
            'parameter_id' => $this->param2->id,
            'status' => 'PASS',
        ]);
    }
}
