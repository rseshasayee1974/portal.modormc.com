<?php

namespace Tests\Feature\QC;

use App\Models\QC\QcUnit;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QCUnitControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $plant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->user->update(['default_plant_id' => $this->plant->id]);
        $this->actingAs($this->user);
    }

    public function test_can_view_units_index()
    {
        QcUnit::factory()->count(3)->create(['plant_id' => $this->plant->id]);

        $response = $this->get(route('quality.config.units.index'));

        $response->assertStatus(200);
    }

    public function test_can_store_unit_via_form()
    {
        $payload = [
            'code' => 'MPA',
            'name' => 'Megapascal',
            'symbol' => 'MPa',
            'dimension' => 'pressure',
            'is_active' => true,
        ];

        $response = $this->post(route('quality.config.units.store'), $payload);

        $response->assertRedirect(route('quality.config.units.index'));
        $this->assertDatabaseHas('mm_qc_units', [
            'code' => 'MPA',  // Preserves case because it's a code
            'name' => 'Megapascal',
            'plant_id' => $this->plant->id,
        ]);
    }

    public function test_can_store_unit_via_json_without_inertia()
    {
        $payload = [
            'code' => 'KN',
            'name' => 'Kilonewton',
            'symbol' => 'kN',
            'dimension' => 'Force',
            'is_active' => true,
        ];

        $response = $this->postJson(route('quality.config.units.store'), $payload);

        $response->assertJsonStructure([
            'success',
            'unit',
            'message',
        ]);
        $response->assertJson(['success' => true]);
    }

    public function test_store_validation_fails_without_required_fields()
    {
        $response = $this->postJson(route('quality.config.units.store'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code', 'name', 'symbol']);
    }

    public function test_can_update_unit()
    {
        $unit = QcUnit::factory()->create([
            'plant_id' => $this->plant->id,
            'name' => 'Old Unit',
            'code' => 'OLD',
            'symbol' => 'o',
        ]);

        $payload = [
            'code' => 'NEW',
            'name' => 'New Unit',
            'symbol' => 'n',
            'dimension' => 'length',
            'is_active' => true,
        ];

        $response = $this->put(route('quality.config.units.update', $unit), $payload);

        $response->assertRedirect(route('quality.config.units.index'));
        $this->assertDatabaseHas('mm_qc_units', [
            'id' => $unit->id,
            'name' => 'New Unit',
        ]);
    }

    public function test_can_delete_unit()
    {
        $unit = QcUnit::factory()->create(['plant_id' => $this->plant->id]);

        $response = $this->delete(route('quality.config.units.destroy', $unit));

        $response->assertStatus(302);
        $this->assertSoftDeleted('mm_qc_units', ['id' => $unit->id]);
    }

    public function test_can_toggle_unit_active_status()
    {
        $unit = QcUnit::factory()->create([
            'plant_id' => $this->plant->id,
            'is_active' => true,
        ]);

        $response = $this->post(route('quality.config.units.toggle', $unit));

        $response->assertStatus(302);
        $unit->refresh();
        $this->assertFalse($unit->is_active);

        // Toggle back
        $this->post(route('quality.config.units.toggle', $unit));
        $unit->refresh();
        $this->assertTrue($unit->is_active);
    }

    public function test_index_filters_by_search()
    {
        QcUnit::factory()->create([
            'plant_id' => $this->plant->id,
            'name' => 'Megapascal',
            'code' => 'MPA',
        ]);
        QcUnit::factory()->create([
            'plant_id' => $this->plant->id,
            'name' => 'Kilonewton',
            'code' => 'KN',
        ]);

        $response = $this->get(route('quality.config.units.index', ['search' => 'Mega']));

        $response->assertStatus(200);
    }
}
