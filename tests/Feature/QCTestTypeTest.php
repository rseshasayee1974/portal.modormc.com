<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Entity;
use App\Models\Plant;
use App\Models\QC\QcTestType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QCTestTypeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Entity $entity;
    protected Plant $plant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = Entity::factory()->create();
        $this->plant = Plant::create([
            'entity_id' => $this->entity->id,
            'code' => 'PLT-QC',
            'name' => 'QC Test Plant',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'default_entity_id' => $this->entity->id,
            'default_plant_id' => $this->plant->id,
        ]);

        session([
            'active_entity_id' => $this->entity->id,
            'active_plant_id' => $this->plant->id,
        ]);
    }

    public function test_can_list_test_types_with_pagination_and_filters(): void
    {
        QcTestType::create([
            'plant_id' => $this->plant->id,
            'code' => 'SLUMP',
            'name' => 'Slump Test',
            'category' => 'Concrete',
            'layout_type' => 'SINGLE_TRIAL',
            'is_active' => true,
        ]);

        QcTestType::create([
            'plant_id' => $this->plant->id,
            'code' => 'SIEVE_AGG',
            'name' => 'Sieve Analysis 20mm',
            'category' => 'Aggregate',
            'layout_type' => 'SIEVE_GRADATION',
            'is_active' => true,
        ]);

        QcTestType::create([
            'plant_id' => $this->plant->id,
            'code' => 'OLD_TEST',
            'name' => 'Deprecated Test',
            'category' => 'General',
            'layout_type' => 'SINGLE_TRIAL',
            'is_active' => false,
        ]);

        // 1. Basic listing
        $response = $this->actingAs($this->user)->get(route('quality.config.test-types.index'));
        $response->assertOk();

        // 2. Search filter
        $response = $this->actingAs($this->user)->get(route('quality.config.test-types.index', [
            'search' => 'Sieve'
        ]));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Quality/Configuration/TestTypes/Index')
            ->has('testTypes.data', 1)
            ->where('testTypes.data.0.code', 'SIEVE_AGG')
        );

        // 3. Category filter
        $response = $this->actingAs($this->user)->get(route('quality.config.test-types.index', [
            'category' => 'Concrete'
        ]));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('testTypes.data', 1)
            ->where('testTypes.data.0.code', 'SLUMP')
        );

        // 4. Status filter
        $response = $this->actingAs($this->user)->get(route('quality.config.test-types.index', [
            'status' => 'Inactive'
        ]));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('testTypes.data', 1)
            ->where('testTypes.data.0.code', 'OLD_TEST')
        );

        // 5. Layout type filter
        $response = $this->actingAs($this->user)->get(route('quality.config.test-types.index', [
            'layout_type' => 'SIEVE_GRADATION'
        ]));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('testTypes.data', 1)
            ->where('testTypes.data.0.code', 'SIEVE_AGG')
        );
    }

    public function test_can_create_and_toggle_test_type(): void
    {
        $response = $this->actingAs($this->user)->post(route('quality.config.test-types.store'), [
            'name' => 'Vicat Setting Time',
            'code' => 'VICAT_SETTING',
            'category' => 'Cement',
            'standard_reference' => 'IS 4031 (Part 5)',
            'calculation_type' => 'formula',
            'layout_type' => 'TIMED_OBSERVATION',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qc_test_types', [
            'code' => 'VICAT_SETTING',
            'layout_type' => 'TIMED_OBSERVATION',
        ]);

        $testType = QcTestType::where('code', 'VICAT_SETTING')->first();

        // Toggle status
        $response = $this->actingAs($this->user)->post(route('quality.config.test-types.toggle', $testType->id));
        $response->assertRedirect();
        $this->assertFalse($testType->fresh()->is_active);
    }
}
