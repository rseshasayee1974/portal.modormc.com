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

        \App\Models\EntityUser::create([
            'user_id' => $this->user->id, 'entity_id' => $entity->id, 'plant_id' => $this->plant->id,
            'role_id' => \App\Models\Role::factory()->create()->id,
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
        $this->assertDatabaseHas('mm_qc_test_results', [
            'qc_test_id' => $this->test->id,
            'parameter_id' => $this->param2->id,
            'status' => 'PASS',
        ]);
    }

    public function test_completed_and_approved_tests_can_be_revised_and_require_a_fresh_review(): void
    {
        foreach (['pass', 'fail', 'retest'] as $status) {
            foreach (['draft', 'approved'] as $approval) {
                $this->test->update([
                    'overall_status' => $status, 'approval_status' => $approval,
                    'evaluated_at' => now(), 'reviewed_by' => $this->user->id, 'reviewed_at' => now(),
                ]);
                $this->actingAs($this->user)->get(route('quality.tests.execute', $this->test))
                    ->assertOk()->assertInertia(fn ($page) => $page->where('readOnly', false));
                $this->actingAs($this->user)->post(route('quality.tests.submit', $this->test), [
                    'test_date' => now()->toDateTimeString(),
                    'measurements' => [['parameter_id' => $this->param1->id, 'value_numeric' => 800, 'row_index' => 0]],
                    'remarks' => 'Revised after '.$status,
                ])->assertRedirect()->assertSessionHasNoErrors();
                $this->test->refresh();
                $this->assertSame('pass', $this->test->overall_status);
                $this->assertSame('draft', $this->test->approval_status);
                $this->assertNull($this->test->reviewed_by);
                $this->assertNull($this->test->reviewed_at);
            }
        }
        $this->get(route('quality.tests.execute', ['test' => $this->test, 'view' => 1]))
            ->assertOk()->assertInertia(fn ($page) => $page->where('readOnly', true));
    }

    public function test_completed_and_approved_tests_can_be_deleted_while_retaining_the_sample(): void
    {
        foreach (['pass', 'fail', 'retest'] as $status) {
            $test = $this->test->replicate();
            $test->fill(['test_no' => 'DELETE-'.$status, 'overall_status' => $status, 'approval_status' => 'approved', 'evaluated_at' => now()]);
            $test->save();
            $this->actingAs($this->user)->delete(route('quality.tests.destroy', $test))->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSoftDeleted('mm_qc_tests', ['id' => $test->id]);
            $this->assertDatabaseHas('mm_qc_samples', ['id' => $this->sample->id, 'deleted_at' => null]);
        }
    }

    public function test_approved_cube_sets_can_be_revised_and_recalculated(): void
    {
        $this->test->update([
            'age_days' => 7, 'overall_status' => 'fail', 'approval_status' => 'approved',
            'evaluated_at' => now(), 'reviewed_by' => $this->user->id, 'reviewed_at' => now(),
        ]);
        $this->test->ensureSetsAndSpecimensInitialized();
        $set = $this->test->sets()->firstOrFail();
        $specimens = $set->specimens()->get()->map(fn ($specimen) => [
            'id' => $specimen->id, 'weight_kg' => 8.1, 'load_kn' => 800,
        ])->all();
        $this->actingAs($this->user)->post(route('quality.tests.submit', $this->test), [
            'test_date' => now()->toDateString(),
            'sets' => [['id' => $set->id, 'testing_date' => now()->toDateString(), 'specimens' => $specimens]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->test->refresh();
        $this->assertSame('pass', $this->test->overall_status);
        $this->assertSame('draft', $this->test->approval_status);
        $this->assertNull($this->test->reviewed_by);
        $this->assertNull($this->test->reviewed_at);
        $this->assertGreaterThan(0, (float) $set->fresh()->average_strength);
    }

    public function test_executing_cube_sets_keeps_sample_testing_until_other_tests_finish(): void
    {
        $pendingTest = $this->test->replicate();
        $pendingTest->fill(['test_no' => 'PENDING-SAMPLE-TEST', 'overall_status' => 'pending']);
        $pendingTest->save();
        $this->test->ensureSetsAndSpecimensInitialized();
        $set = $this->test->sets()->firstOrFail();
        $specimens = $set->specimens()->get()->map(fn ($specimen) => [
            'id' => $specimen->id, 'weight_kg' => 8.1, 'load_kn' => 800,
        ])->all();
        $this->actingAs($this->user)->post(route('quality.tests.submit', $this->test), [
            'test_date' => now()->toDateString(),
            'sets' => [['id' => $set->id, 'testing_date' => now()->toDateString(), 'specimens' => $specimens]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('testing', $this->sample->fresh()->status);
        $this->assertSame('pending', $pendingTest->fresh()->overall_status);
    }

    public function test_new_test_ages_follow_the_selected_type_parameters(): void
    {
        QcTestParameter::create([
            'test_type_id' => $this->testType->id, 'name' => '3-Day Strength', 'code' => 'STRENGTH_3D',
            'is_active' => true, 'target_value' => 12, 'min_value' => 10,
        ]);
        $payload = ['sample_id' => $this->sample->id, 'test_type_id' => $this->testType->id, 'schedule_milestones' => [3]];
        $this->actingAs($this->user)->post(route('quality.tests.store'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mm_qc_tests', [
            'sample_id' => $this->sample->id, 'test_type_id' => $this->testType->id,
            'age_days' => 3, 'target_strength' => 12, 'min_strength' => 10,
        ]);
        $this->actingAs($this->user)->post(route('quality.tests.store'), array_replace($payload, ['schedule_milestones' => [15]]))
            ->assertSessionHasErrors('schedule_milestones');
    }

    public function test_sample_casting_date_can_be_edited_after_an_approved_test(): void
    {
        $this->test->update(['age_days' => 7, 'overall_status' => 'pass', 'approval_status' => 'approved', 'evaluated_at' => now()]);
        $set = $this->test->sets()->create([
            'set_number' => 1, 'age_days' => 7, 'casting_date' => $this->sample->sample_date,
            'testing_date' => now(), 'status' => 'pass',
        ]);
        $actualTestDate = $set->testing_date->toDateTimeString();
        $castingDate = now()->subDays(10)->toDateString();
        $this->actingAs($this->user)->put(route('quality.samples.update', $this->sample), [
            'sample_date' => $castingDate, 'remarks' => 'Corrected casting date',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($castingDate, $this->sample->fresh()->sample_date->toDateString());
        $this->assertSame(now()->subDays(3)->toDateString(), $this->test->fresh()->scheduled_date->toDateString());
        $this->assertSame($castingDate, $set->fresh()->casting_date->toDateString());
        $this->assertSame($actualTestDate, $set->fresh()->testing_date->toDateTimeString());
    }

    public function test_certificate_averages_all_specimens_and_shows_percentage_of_grade_strength(): void
    {
        $grade = \App\Models\ConcreteGrade::create(['plant_id' => $this->plant->id, 'name' => 'M40', 'status' => true]);
        $this->sample->update(['concrete_grade_id' => $grade->id]);
        $this->test->update(['overall_status' => 'fail', 'age_days' => 28]);
        $set = $this->test->sets()->create([
            'set_number' => 1, 'age_days' => 28, 'casting_date' => '2026-09-18',
            'testing_date' => '2026-10-16', 'status' => 'fail', 'average_strength' => 999,
        ]);
        foreach ([24, 22.71, 28.89, 30.22] as $index => $strength) {
            $set->specimens()->create([
                'qc_test_id' => $this->test->id, 'specimen_index' => $index + 1,
                'set_specimen_index' => $index + 1, 'weight_kg' => 8, 'load_kn' => $strength * 22.5,
                'strength_mpa' => $strength, 'status' => 'completed',
            ]);
        }
        $report = app(\App\Services\QC\CubeReportDataService::class)->certificateSets($this->test->fresh())->sole();
        $this->assertEquals(26.46, $report->average_strength);
        $this->assertEquals(66.15, $report->grade_strength_percentage);
        $this->assertSame(100, $report->age_reference_percentage);
        $this->assertEquals(999, $set->fresh()->average_strength);
        $html = view('reports.concrete_strength_certificate_pdf', [
            'test' => $this->test->fresh(), 'reportSets' => collect([$report]), 'plant' => $this->plant,
            'companyName' => 'Example Concrete', 'companyAddress' => 'Test Plant', 'companyLogo' => null,
            'clientName' => 'Example Client', 'siteAddress' => 'Example Site', 'projectName' => 'Example Project',
            'gradeName' => 'M40', 'reportDate' => '24/10/2026',
        ])->render();
        $this->assertStringContainsString('Specimen size (L x W x H):', $html);
        $this->assertStringContainsString('150 x 150 x 150 mm', $html);
        $this->assertStringContainsString('66.2%', $html);
        $this->assertStringContainsString('Required / target', $html);
        $this->assertStringNotContainsString('<th>L</th>', $html);
        if (getenv('QC_CERTIFICATE_PREVIEW')) {
            \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait')->save(getenv('QC_CERTIFICATE_PREVIEW'));
        }
    }

    public function test_certificate_supports_one_specimen_and_age_percentage_references(): void
    {
        $grade = \App\Models\ConcreteGrade::create(['plant_id' => $this->plant->id, 'name' => 'M40', 'status' => true]);
        $this->sample->update(['concrete_grade_id' => $grade->id]);
        $this->test->update(['overall_status' => 'pass']);
        $set = $this->test->sets()->create(['set_number' => 1, 'status' => 'pass', 'casting_date' => '2026-09-01']);
        $set->specimens()->create([
            'qc_test_id' => $this->test->id, 'specimen_index' => 1, 'set_specimen_index' => 1,
            'weight_kg' => 8, 'load_kn' => 720, 'strength_mpa' => 32, 'status' => 'completed',
        ]);
        foreach ([7 => 67, 14 => 90, 28 => 100, 15 => null] as $age => $expected) {
            $set->update(['testing_date' => \Carbon\Carbon::parse('2026-09-01')->addDays($age)]);
            $report = app(\App\Services\QC\CubeReportDataService::class)->certificateSets($this->test->fresh())->sole();
            $this->assertEquals(32, $report->average_strength);
            $this->assertEquals(80, $report->grade_strength_percentage);
            $this->assertSame($expected, $report->age_reference_percentage);
        }
        $this->sample->update(['concrete_grade_id' => null]);
        $report = app(\App\Services\QC\CubeReportDataService::class)->certificateSets($this->test->fresh())->sole();
        $this->assertNull($report->grade_strength_percentage);
    }

    public function test_certificate_target_columns_match_the_execution_benchmark(): void
    {
        $grade = \App\Models\ConcreteGrade::create(['plant_id' => $this->plant->id, 'name' => 'M40', 'status' => true]);
        $this->sample->update(['concrete_grade_id' => $grade->id]);
        $this->test->update(['overall_status' => 'fail', 'target_strength' => 28]);
        $set = $this->test->sets()->create([
            'set_number' => 1, 'status' => 'fail', 'casting_date' => '2026-09-01', 'testing_date' => '2026-09-08',
        ]);
        $set->specimens()->create([
            'qc_test_id' => $this->test->id, 'specimen_index' => 1, 'set_specimen_index' => 1,
            'weight_kg' => 8.5, 'load_kn' => 522, 'strength_mpa' => 23.2, 'status' => 'completed',
        ]);
        $report = app(\App\Services\QC\CubeReportDataService::class)->certificateSets($this->test->fresh())->sole();
        $this->assertEquals(23.2, $report->average_strength);
        $this->assertEquals(28, $report->required_strength_mpa);
        $this->assertEquals(82.9, $report->target_achieved_percentage);
        $grade->update(['name' => 'M25']);
        $this->test->update(['target_strength' => null]);
        $report = app(\App\Services\QC\CubeReportDataService::class)->certificateSets($this->test->fresh())->sole();
        $this->assertEquals(16.75, $report->required_strength_mpa);
        $this->assertEquals(138.5, $report->target_achieved_percentage);
    }
}
