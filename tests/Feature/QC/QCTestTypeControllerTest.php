<?php

namespace Tests\Feature\QC;

use App\Models\QC\QcTestType;
use App\Models\QC\QcTestParameter;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class QCTestTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $plant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup initial user and plant context
        $this->user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        
        $this->user->update(['default_plant_id' => $this->plant->id]);
        $this->actingAs($this->user);
    }

    public function test_can_view_index()
    {
        QcTestType::factory()->count(3)->create(['plant_id' => $this->plant->id]);

        $response = $this->get(route('quality.config.test-types.index'));

        $response->assertStatus(200);
    }

    public function test_can_store_test_type()
    {
        $payload = [
            'name' => 'Compressive Strength Test',
            'code' => 'COMP_STR',
            'category' => 'Concrete',
            'material_type' => 'Concrete',
            'standard_reference' => 'IS 516',
            'calculation_type' => 'formula',
            'layout_type' => 'SINGLE_TRIAL',
            'specimen_count' => 3,
            'is_active' => true,
            'parameters' => [
                [
                    'name' => 'Load',
                    'code' => 'LOAD',
                    'scope' => 'test', // THIS is where the validation failed previously
                    'data_type' => 'numeric',
                    'unit' => 'kN',
                    'is_required' => true,
                ]
            ]
        ];

        $response = $this->postJson(route('quality.config.test-types.store'), $payload);

        $response->assertStatus(302); // Redirects back or to index usually
        
        $this->assertDatabaseHas('mm_qc_test_types', [
            'name' => 'Compressive Strength Test',
            'code' => 'COMP_STR',
            'plant_id' => $this->plant->id,
        ]);

        $this->assertDatabaseHas('mm_qc_test_parameters', [
            'name' => 'Load',
            'code' => 'LOAD',
            'scope' => 'test',
        ]);
    }

    public function test_scope_validation_allows_specimen_and_summary()
    {
        $payload = [
            'name' => 'Test with different scopes',
            'code' => 'TEST_SCOPES',
            'category' => 'Concrete',
            'material_type' => 'Concrete',
            'parameters' => [
                [
                    'name' => 'Param 1',
                    'code' => 'P1',
                    'scope' => 'specimen', 
                    'data_type' => 'numeric',
                ],
                [
                    'name' => 'Param 2',
                    'code' => 'P2',
                    'scope' => 'summary', 
                    'data_type' => 'numeric',
                ]
            ]
        ];

        $response = $this->postJson(route('quality.config.test-types.store'), $payload);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        
        $this->assertDatabaseHas('mm_qc_test_parameters', [
            'scope' => 'specimen',
        ]);
        $this->assertDatabaseHas('mm_qc_test_parameters', [
            'scope' => 'summary',
        ]);
    }

    public function test_can_update_test_type()
    {
        $testType = QcTestType::factory()->create([
            'plant_id' => $this->plant->id,
            'name' => 'Old Name',
        ]);

        $payload = [
            'name' => 'New Name',
            'code' => $testType->code,
            'category' => 'Concrete',
            'material_type' => 'Concrete',
            'is_active' => true,
            'parameters' => []
        ];

        $response = $this->putJson(route('quality.config.test-types.update', $testType->id), $payload);

        $response->assertStatus(302);
        $this->assertDatabaseHas('mm_qc_test_types', [
            'id' => $testType->id,
            'name' => 'New Name',
        ]);
    }

    public function test_can_delete_test_type()
    {
        $testType = QcTestType::factory()->create([
            'plant_id' => $this->plant->id,
        ]);

        $response = $this->delete(route('quality.config.test-types.destroy', $testType->id));

        $response->assertStatus(302);
        $this->assertSoftDeleted('mm_qc_test_types', [
            'id' => $testType->id,
        ]);
    }
}
