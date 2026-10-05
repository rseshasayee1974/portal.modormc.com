<?php

namespace Tests\Feature;

use App\Models\{ConcreteQualityTest, Patron, Personnel, Plant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

class ConcreteQualityDropdownTest extends TestCase
{
    use RefreshDatabase;

    private Plant $plant;
    private Plant $otherPlant;
    private Patron $patron;
    private Personnel $person;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->otherPlant = Plant::factory()->create();
        $this->patron = Patron::factory()->create(['plant_id' => $this->plant->id]);
        Patron::factory()->create(['plant_id' => $this->otherPlant->id]);
        $this->person = Personnel::factory()->create(['plant_id' => $this->plant->id]);
        Personnel::factory()->create(['plant_id' => $this->otherPlant->id]);
        $user->assignRole(Role::firstOrCreate(['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']));
        $this->actingAs($user)->withSession(['active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id]);
    }

    private function payload(): array
    {
        return [
            'plant_id' => $this->plant->id, 'patron_id' => $this->patron->id, 'account_name' => 'Client-supplied name',
            'grade' => 'M25', 'concrete_date' => '2026-10-03', 'age_of_test_days' => 7,
            'date_of_testing' => '2026-10-10', 'dimension_length' => 15, 'dimension_width' => 15,
            'dimension_height' => 15, 'slump_value' => 120, 'fresh_temperature' => 32, 'air_content' => 1.2,
            'lab_technician' => trim($this->person->first_name . ' ' . $this->person->last_name),
            'field_technician' => trim($this->person->first_name . ' ' . $this->person->last_name),
        ];
    }

    public function test_form_options_only_include_active_plant_patrons_and_personnel(): void
    {
        $this->get(route('concrete-quality-tests.create'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ConcreteQualityTests/Create')
            ->has('plants', 1)->where('plants.0.id', $this->plant->id)
            ->has('patrons', 1)->where('patrons.0.id', $this->patron->id)
            ->has('personnels', 1)->where('personnels.0.id', $this->person->id));
    }

    public function test_selected_patron_name_and_technicians_save_and_reopen(): void
    {
        $this->post(route('concrete-quality-tests.store'), $this->payload())->assertSessionHasNoErrors();
        $test = ConcreteQualityTest::firstOrFail();
        $this->assertEquals($this->patron->name, $test->account_name);
        $this->assertEquals($this->patron->id, $test->patron_id);
        $this->assertEquals($this->payload()['lab_technician'], $test->lab_technician);
        $this->get(route('concrete-quality-tests.edit', $test))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ConcreteQualityTests/Edit')->where('activePlantId', $this->plant->id)
            ->has('plants', 1)->has('personnels', 1)->where('test.patron_id', $this->patron->id));
        $payload = $this->payload();
        $payload['field_technician'] = null;
        $this->put(route('concrete-quality-tests.update', $test), $payload)->assertSessionHasNoErrors();
        $this->assertNull($test->fresh()->field_technician);
    }

    public function test_foreign_factory_and_patron_cannot_be_submitted(): void
    {
        $payload = $this->payload();
        $payload['plant_id'] = $this->otherPlant->id;
        $this->post(route('concrete-quality-tests.store'), $payload)->assertSessionHasErrors('plant_id');
        $payload = $this->payload();
        $payload['patron_id'] = Patron::withoutPlantScope()->where('plant_id', $this->otherPlant->id)->firstOrFail()->id;
        $this->post(route('concrete-quality-tests.store'), $payload)->assertSessionHasErrors('patron_id');
        $this->assertDatabaseCount('mm_concrete_quality_tests', 0);
    }
}
