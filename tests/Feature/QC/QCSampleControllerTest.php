<?php

namespace Tests\Feature\QC;

use App\Models\QC\QcSample;
use App\Models\QC\QcTestType;
use App\Models\QC\QcTestParameter;
use App\Models\QC\QcTest;
use App\Models\ConcreteGrade;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QCSampleControllerTest extends TestCase
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

    public function test_can_view_samples_index()
    {
        $response = $this->get(route('quality.samples.index'));

        $response->assertStatus(200);
    }

    public function test_can_view_create_form()
    {
        $response = $this->get(route('quality.samples.create'));

        $response->assertStatus(200);
    }

    public function test_can_store_sample_with_milestones()
    {
        // Create a concrete grade and matching test type
        $grade = ConcreteGrade::factory()->create(['name' => 'M25', 'status' => true]);
        $testType = QcTestType::factory()->create([
            'plant_id' => $this->plant->id,
            'category' => 'Concrete',
            'name' => 'Concrete Compressive Strength',
            'material_type' => $grade->name,
        ]);

        $payload = [
            'sample_date' => now()->toDateString(),
            'concrete_grade_id' => $grade->id,
            'specimen_count' => 9,
            'specimen_size' => '150x150x150 mm',
            'schedule_milestones' => [7, 15, 28],
            'test_type_ids' => [$testType->id],
        ];

        $response = $this->post(route('quality.samples.store'), $payload);

        $response->assertRedirect(route('quality.samples.index'));

        // Should have created 1 sample
        $this->assertDatabaseCount('mm_qc_samples', 1);

        // Should have created 3 tests (one per milestone)
        $sample = QcSample::first();
        $this->assertEquals(3, QcTest::where('sample_id', $sample->id)->count());
    }

    public function test_can_store_sample_without_milestones_defaults_to_28_day()
    {
        $grade = ConcreteGrade::factory()->create(['name' => 'M30', 'status' => true]);
        $testType = QcTestType::factory()->create([
            'plant_id' => $this->plant->id,
            'category' => 'Concrete',
            'name' => 'Concrete Compressive Test',
            'material_type' => $grade->name,
        ]);

        $payload = [
            'sample_date' => now()->toDateString(),
            'concrete_grade_id' => $grade->id,
            'test_type_ids' => [$testType->id],
        ];

        $response = $this->post(route('quality.samples.store'), $payload);

        $response->assertRedirect(route('quality.samples.index'));

        $sample = QcSample::first();
        $this->assertNotNull($sample);
        // At least 1 test should be created
        $this->assertGreaterThanOrEqual(1, QcTest::where('sample_id', $sample->id)->count());
    }

    public function test_store_validation_requires_sample_date()
    {
        $response = $this->postJson(route('quality.samples.store'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sample_date']);
    }

    public function test_can_update_sample()
    {
        $grade = ConcreteGrade::factory()->create(['status' => true]);
        $sample = QcSample::factory()->create([
            'plant_id' => $this->plant->id,
            'concrete_grade_id' => $grade->id,
            'site_name' => 'Old Site',
        ]);

        $payload = [
            'sample_date' => now()->toDateString(),
            'site_name' => 'New Site',
            'concrete_grade_id' => $grade->id,
        ];

        $response = $this->put(route('quality.samples.update', $sample), $payload);

        $response->assertRedirect(route('quality.samples.index'));
        $this->assertDatabaseHas('mm_qc_samples', [
            'id' => $sample->id,
            'site_name' => 'New Site',
        ]);
    }

    public function test_can_delete_sample()
    {
        $grade = ConcreteGrade::factory()->create(['status' => true]);
        $sample = QcSample::factory()->create([
            'plant_id' => $this->plant->id,
            'concrete_grade_id' => $grade->id,
        ]);

        $response = $this->delete(route('quality.samples.destroy', $sample));

        $response->assertRedirect(route('quality.samples.index'));
        $this->assertSoftDeleted('mm_qc_samples', ['id' => $sample->id]);
    }

    public function test_sample_generates_unique_sample_no()
    {
        $grade = ConcreteGrade::factory()->create(['name' => 'M20', 'status' => true]);
        $testType = QcTestType::factory()->create([
            'plant_id' => $this->plant->id,
            'category' => 'Concrete',
            'name' => 'Compressive',
        ]);

        // Create two samples
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('quality.samples.store'), [
                'sample_date' => now()->toDateString(),
                'concrete_grade_id' => $grade->id,
                'test_type_ids' => [$testType->id],
                'schedule_milestones' => [28],
            ]);
        }

        $samples = QcSample::all();
        $this->assertEquals(2, $samples->count());
        $this->assertNotEquals($samples[0]->sample_no, $samples[1]->sample_no);
    }
}
