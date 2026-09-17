<?php

namespace Tests\Feature\QC;

use App\Models\QC\QcTestParameter;
use App\Models\QC\QcTestType;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QCTestParameterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $plant;
    protected $testType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->user->update(['default_plant_id' => $this->plant->id]);
        $this->actingAs($this->user);

        $this->testType = QcTestType::factory()->create([
            'plant_id' => $this->plant->id,
        ]);
    }

    public function test_can_view_parameters_index()
    {
        QcTestParameter::factory()->count(3)->create([
            'test_type_id' => $this->testType->id,
        ]);

        $response = $this->get(route('quality.config.test-parameters.index'));

        $response->assertStatus(200);
    }

    public function test_can_store_parameter()
    {
        $payload = [
            'test_type_id' => $this->testType->id,
            'code' => 'LOAD_KN',
            'name' => 'Load Applied',
            'data_type' => 'numeric',
            'unit' => 'kN',
            'is_required' => true,
            'is_calculated' => false,
            'display_order' => 1,
        ];

        $response = $this->post(route('quality.config.test-parameters.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_qc_test_parameters', [
            'code' => 'LOAD_KN',
            'test_type_id' => $this->testType->id,
        ]);
    }

    public function test_store_with_acceptance_rule()
    {
        $payload = [
            'test_type_id' => $this->testType->id,
            'code' => 'STRENGTH',
            'name' => 'Compressive Strength',
            'data_type' => 'numeric',
            'unit' => 'MPa',
            'is_required' => true,
            'is_calculated' => false,
            'display_order' => 1,
            'rule_type' => 'GREATER_THAN_OR_EQUAL',
            'min_value' => 25.0,
        ];

        $response = $this->post(route('quality.config.test-parameters.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_qc_test_parameters', [
            'code' => 'STRENGTH',
            'rule_type' => 'GREATER_THAN_OR_EQUAL',
        ]);
    }

    public function test_store_validation_requires_test_type_and_name()
    {
        $response = $this->postJson(route('quality.config.test-parameters.store'), [
            'code' => 'X',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['test_type_id', 'name']);
    }

    public function test_can_update_parameter()
    {
        $param = QcTestParameter::factory()->create([
            'test_type_id' => $this->testType->id,
            'name' => 'Old Param',
            'code' => 'OLD_P',
        ]);

        $payload = [
            'code' => 'NEW_P',
            'name' => 'New Param',
            'data_type' => 'numeric',
            'is_required' => true,
            'is_calculated' => false,
            'display_order' => 2,
        ];

        $response = $this->put(route('quality.config.test-parameters.update', $param), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_qc_test_parameters', [
            'id' => $param->id,
            'code' => 'NEW_P',
            'name' => 'New Param',
        ]);
    }

    public function test_can_delete_parameter()
    {
        $param = QcTestParameter::factory()->create([
            'test_type_id' => $this->testType->id,
        ]);

        $response = $this->delete(route('quality.config.test-parameters.destroy', $param));

        $response->assertStatus(302);
        $this->assertSoftDeleted('mm_qc_test_parameters', ['id' => $param->id]);
    }

    public function test_store_with_range_rule_type()
    {
        $payload = [
            'test_type_id' => $this->testType->id,
            'code' => 'SLUMP',
            'name' => 'Slump Value',
            'data_type' => 'numeric',
            'unit' => 'mm',
            'is_required' => true,
            'is_calculated' => false,
            'display_order' => 1,
            'rule_type' => 'RANGE',
            'min_value' => 50,
            'max_value' => 150,
        ];

        $response = $this->post(route('quality.config.test-parameters.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_qc_test_parameters', [
            'code' => 'SLUMP',
            'rule_type' => 'RANGE',
        ]);
    }

    public function test_formatted_rule_description()
    {
        $param = QcTestParameter::factory()->create([
            'test_type_id' => $this->testType->id,
            'rule_type' => QcTestParameter::RULE_TYPE_RANGE,
            'min_value' => 10.0,
            'max_value' => 50.0,
            'unit' => 'MPa',
        ]);

        $this->assertNotNull($param->formattedRuleDescription());
        $this->assertStringContainsString('10', $param->formattedRuleDescription());
        $this->assertStringContainsString('50', $param->formattedRuleDescription());
    }
}
