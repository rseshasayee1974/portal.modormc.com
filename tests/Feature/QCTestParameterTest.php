<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Entity;
use App\Models\QC\QcTestType;
use App\Models\QC\QcTestParameter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QCTestParameterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Entity $entity;
    protected QcTestType $testType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = Entity::factory()->create();
        $plant = \App\Models\Plant::create([
            'entity_id' => $this->entity->id,
            'code' => 'PLT-01',
            'name' => 'Main Test Plant',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'default_entity_id' => $this->entity->id,
            'default_plant_id' => $plant->id,
        ]);

        session([
            'active_entity_id' => $this->entity->id,
            'active_plant_id' => $plant->id,
        ]);

        $this->testType = QcTestType::create([
            'plant_id' => $plant->id,
            'code' => 'SLUMP_TEST',
            'name' => 'Slump Test',
            'category' => 'Concrete',
            'layout_type' => 'SINGLE_TRIAL',
            'is_active' => true,
        ]);
    }

    public function test_can_list_test_parameters(): void
    {
        QcTestParameter::create([
            'test_type_id' => $this->testType->id,
            'code' => 'SLUMP_VAL',
            'name' => 'Slump Value',
            'data_type' => 'decimal',
            'unit' => 'mm',
            'is_required' => true,
            'is_calculated' => false,
            'display_order' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('quality.config.test-parameters.index', ['test_type_id' => $this->testType->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Quality/Configuration/Parameters/Index')
                ->has('parameters', 1)
        );
    }

    public function test_can_create_test_parameter(): void
    {
        $payload = [
            'test_type_id' => $this->testType->id,
            'code' => 'WET_WEIGHT',
            'name' => 'Weight of Wet Sample',
            'data_type' => 'Formula',
            'unit' => 'g',
            'is_required' => true,
            'is_calculated' => true,
            'formula' => 'A + B',
            'display_order' => 1,
        ];

        $targetUrl = route('quality.config.test-parameters.index', ['test_type_id' => $this->testType->id]);

        $response = $this->actingAs($this->user)
            ->from($targetUrl)
            ->post(route('quality.config.test-parameters.store'), $payload);

        $response->assertRedirect($targetUrl);

        $this->assertDatabaseHas('qc_test_parameters', [
            'test_type_id' => $this->testType->id,
            'code' => 'WET_WEIGHT',
            'name' => 'Weight Of Wet Sample',
            'data_type' => 'Formula',
            'unit' => 'G',
        ]);
    }

    public function test_can_update_test_parameter(): void
    {
        $param = QcTestParameter::create([
            'test_type_id' => $this->testType->id,
            'code' => 'OLD_CODE',
            'name' => 'Old Name',
            'data_type' => 'decimal',
            'unit' => 'g',
            'display_order' => 1,
        ]);

        $updatePayload = [
            'code' => 'NEW_CODE',
            'name' => 'Updated Name',
            'data_type' => 'decimal',
            'unit' => 'kg',
            'is_required' => true,
            'is_calculated' => false,
            'display_order' => 2,
        ];

        $targetUrl = route('quality.config.test-parameters.index', ['test_type_id' => $this->testType->id]);

        $response = $this->actingAs($this->user)
            ->from($targetUrl)
            ->put(route('quality.config.test-parameters.update', $param->id), $updatePayload);

        $response->assertRedirect($targetUrl);

        $this->assertDatabaseHas('qc_test_parameters', [
            'id' => $param->id,
            'code' => 'NEW_CODE',
            'name' => 'Updated Name',
            'unit' => 'Kg',
        ]);
    }

    public function test_can_delete_test_parameter(): void
    {
        $param = QcTestParameter::create([
            'test_type_id' => $this->testType->id,
            'code' => 'PARAM_DEL',
            'name' => 'To Be Deleted',
            'data_type' => 'decimal',
            'display_order' => 1,
        ]);

        $targetUrl = route('quality.config.test-parameters.index', ['test_type_id' => $this->testType->id]);

        $response = $this->actingAs($this->user)
            ->from($targetUrl)
            ->delete(route('quality.config.test-parameters.destroy', $param->id));

        $response->assertRedirect($targetUrl);

        $this->assertSoftDeleted('qc_test_parameters', [
            'id' => $param->id,
        ]);
    }

    public function test_can_save_and_retrieve_default_value(): void
    {
        $payload = [
            'test_type_id' => $this->testType->id,
            'code' => 'SPECIMEN_AGE',
            'name' => 'Specimen Curing Age',
            'data_type' => 'integer',
            'unit' => 'Days',
            'default_value' => '7',
            'is_required' => true,
            'is_calculated' => false,
            'display_order' => 1,
        ];

        $targetUrl = route('quality.config.test-parameters.index', ['test_type_id' => $this->testType->id]);

        $response = $this->actingAs($this->user)
            ->from($targetUrl)
            ->post(route('quality.config.test-parameters.store'), $payload);

        $response->assertRedirect($targetUrl);

        $this->assertDatabaseHas('qc_test_parameters', [
            'test_type_id' => $this->testType->id,
            'code' => 'SPECIMEN_AGE',
            'default_value' => '7',
        ]);
    }

    public function test_can_create_parameter_with_acceptance_criteria(): void
    {
        $payload = [
            'test_type_id' => $this->testType->id,
            'code' => 'COMP_STRENGTH_28D',
            'name' => '28 Day Compressive Strength',
            'data_type' => 'decimal',
            'unit' => 'N/mm²',
            'is_required' => true,
            'is_calculated' => false,
            'display_order' => 1,
            'rule_type' => 'GREATER_THAN_OR_EQUAL',
            'min_value' => 25.0,
            'standard_reference' => 'IS 516',
        ];

        $targetUrl = route('quality.config.test-parameters.index', ['test_type_id' => $this->testType->id]);

        $response = $this->actingAs($this->user)
            ->from($targetUrl)
            ->post(route('quality.config.test-parameters.store'), $payload);

        $response->assertRedirect($targetUrl);

        $this->assertDatabaseHas('qc_test_parameters', [
            'test_type_id' => $this->testType->id,
            'code' => 'COMP_STRENGTH_28D',
            'rule_type' => 'GREATER_THAN_OR_EQUAL',
            'min_value' => 25.0000,
            'standard_reference' => 'IS 516',
        ]);
    }

    public function test_can_update_parameter_acceptance_criteria(): void
    {
        $param = QcTestParameter::create([
            'test_type_id' => $this->testType->id,
            'code' => 'SLUMP_MM',
            'name' => 'Slump Test',
            'data_type' => 'decimal',
            'unit' => 'mm',
            'display_order' => 1,
            'rule_type' => 'RANGE',
            'min_value' => 75.0,
            'max_value' => 125.0,
            'standard_reference' => 'IS 1199',
        ]);

        $updatePayload = [
            'code' => 'SLUMP_MM',
            'name' => 'Slump Test (Updated)',
            'data_type' => 'decimal',
            'unit' => 'mm',
            'is_required' => true,
            'is_calculated' => false,
            'display_order' => 1,
            'rule_type' => 'TARGET_TOLERANCE',
            'target_value' => 100.0,
            'tolerance' => 25.0,
            'standard_reference' => 'IS 1199 (Revised)',
        ];

        $targetUrl = route('quality.config.test-parameters.index', ['test_type_id' => $this->testType->id]);

        $response = $this->actingAs($this->user)
            ->from($targetUrl)
            ->put(route('quality.config.test-parameters.update', $param->id), $updatePayload);

        $response->assertRedirect($targetUrl);

        $this->assertDatabaseHas('qc_test_parameters', [
            'id' => $param->id,
            'rule_type' => 'TARGET_TOLERANCE',
            'target_value' => 100.0000,
            'tolerance' => 25.0000,
            'standard_reference' => 'IS 1199 (Revised)',
        ]);
    }
}
