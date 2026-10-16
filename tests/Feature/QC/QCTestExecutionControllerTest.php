<?php

namespace Tests\Feature\QC;

use App\Models\QC\QcTest;
use App\Models\QC\QcTestType;
use App\Models\QC\QcTestParameter;
use App\Models\QC\QcSample;
use App\Models\ConcreteGrade;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QCTestExecutionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $plant;
    protected $testType;
    protected $sample;
    protected $test;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->user->update(['default_plant_id' => $this->plant->id, 'default_entity_id' => $this->plant->entity_id]);
        \App\Models\EntityUser::create([
            'user_id' => $this->user->id, 'entity_id' => $this->plant->entity_id, 'plant_id' => $this->plant->id,
            'role_id' => \App\Models\Role::factory()->create()->id,
        ]);
        $this->actingAs($this->user);
        $this->withSession(['active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id]);

        $grade = ConcreteGrade::factory()->create(['name' => 'M25', 'status' => true]);

        $this->testType = QcTestType::factory()->create([
            'plant_id' => $this->plant->id,
            'category' => 'Concrete',
            'name' => 'Concrete Compressive Strength',
        ]);

        $this->sample = QcSample::factory()->create([
            'plant_id' => $this->plant->id,
            'concrete_grade_id' => $grade->id,
            'status' => 'pending_test',
        ]);

        $this->test = QcTest::factory()->create([
            'plant_id' => $this->plant->id,
            'sample_id' => $this->sample->id,
            'test_type_id' => $this->testType->id,
            'overall_status' => 'pending',
            'age_days' => 7,
            'target_strength' => 17.5,
            'min_strength' => 15.0,
        ]);
    }

    public function test_view_action_opens_test_in_read_only_mode()
    {
        $this->get(route('quality.tests.execute', ['test' => $this->test->id, 'view' => 1]))
            ->assertInertia(fn ($page) => $page->component('Quality/Tests/Execute')->where('readOnly', true));
    }

    public function test_delete_action_keeps_the_sample_and_other_tests()
    {
        $other = QcTest::factory()->create([
            'plant_id' => $this->plant->id, 'sample_id' => $this->sample->id,
            'test_type_id' => $this->testType->id, 'age_days' => 28,
        ]);
        $this->test->update(['approval_status' => 'draft']);
        $this->delete(route('quality.tests.destroy', $this->test))->assertRedirect(route('quality.samples.index'));
        $this->assertSoftDeleted($this->test);
        $this->assertNotNull($this->sample->fresh());
        $this->assertNotNull($other->fresh());
    }

    public function test_approved_test_cannot_be_deleted()
    {
        $this->test->update(['approval_status' => 'approved']);
        $this->deleteJson(route('quality.tests.destroy', $this->test))->assertUnprocessable();
        $this->assertNotNull($this->test->fresh());
    }

    public function test_multiple_selected_ages_create_independent_schedules_atomically(): void
    {
        $payload = ['sample_id' => $this->sample->id, 'test_type_id' => $this->testType->id, 'schedule_milestones' => [28, 15]];
        $this->post(route('quality.tests.store'), $payload)->assertSessionHasNoErrors();
        $tests = $this->sample->tests()->orderBy('age_days')->get();
        $this->assertSame([7, 15, 28], $tests->pluck('age_days')->all());
        foreach ($tests as $test) {
            $this->assertSame($this->sample->scheduledTestDate($test->age_days), $test->scheduled_date->toDateString());
        }
        $this->assertSame(3, $tests->pluck('test_no')->unique()->count());
        $this->postJson(route('quality.tests.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('schedule_milestones');
        $this->assertSame(3, $this->sample->tests()->count());
    }

    public function test_multi_age_payload_rejects_invalid_empty_duplicate_and_conflicting_ages(): void
    {
        $payload = ['sample_id' => $this->sample->id, 'test_type_id' => $this->testType->id];
        foreach ([[], [15, 15], [15, 14], [0], [366], ['fifteen'], [7, 15, 28, 30], '15'] as $ages) {
            $this->postJson(route('quality.tests.store'), $payload + ['schedule_milestones' => $ages])->assertUnprocessable();
            $this->assertSame(1, $this->sample->tests()->count());
        }
        $this->postJson(route('quality.tests.store'), $payload + ['age_days' => 15, 'schedule_milestones' => [28]])
            ->assertUnprocessable()->assertJsonValidationErrors('age_days');
        $this->postJson(route('quality.tests.store'), $payload + ['schedule_milestones' => [7, 15]])
            ->assertUnprocessable()->assertJsonValidationErrors('schedule_milestones');
        $this->assertSame(1, $this->sample->tests()->count());
    }

    public function test_pending_test_cannot_be_approved_or_approved_test_marked_for_retest(): void
    {
        $this->postJson(route('quality.tests.approve', $this->test))->assertUnprocessable();
        $this->test->update(['approval_status' => 'approved']);
        $this->postJson(route('quality.tests.retest', $this->test), ['retest_reason' => 'Repeat'])
            ->assertUnprocessable();
    }

    public function test_high_precision_cube_metadata_is_compact_and_keeps_two_decimal_values(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\TitleCaseInputs::class);
        $column = collect(\Illuminate\Support\Facades\Schema::getColumns('mm_qc_test_measurements'))->firstWhere('name', 'value_text');
        $this->assertSame('text', strtolower($column['type_name']));
        $originalPrecision = ini_get('serialize_precision');
        try {
            ini_set('serialize_precision', '100');
            $this->post(route('quality.tests.submit', $this->test), [
                'test_date' => now()->toDateString(),
                'concrete_specimens' => array_fill(0, 3, [
                    'ident_mark' => rtrim(str_repeat('Cube mark ', 10)),
                    'weight_kg' => 8.2, 'load_kn' => 550,
                    'failure_type' => 'Normal Non-explosive',
                ]),
            ])->assertSessionHasNoErrors()->assertRedirect();
        } finally {
            ini_set('serialize_precision', $originalPrecision);
        }
        $measurements = $this->test->measurements()->orderBy('row_index')->get();
        $this->assertCount(3, $measurements);
        foreach ($measurements as $measurement) {
            $this->assertLessThanOrEqual(255, strlen($measurement->value_text));
            $meta = json_decode($measurement->value_text, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(rtrim(str_repeat('Cube mark ', 10)), $meta['ident_mark']);
            $this->assertSame('8.20', $meta['weight_kg']);
            $this->assertSame('550.00', $meta['load_kn']);
            $this->assertSame('24.44', $meta['strength_mpa']);
            $this->assertSame('2429.63', $meta['density']);
            $this->assertEquals(550, $meta['load_kn']);
            $this->assertEqualsWithDelta(24.44, (float) $meta['strength_mpa'], 0.001);
            $this->assertSame('Normal Non-explosive', $meta['failure_type']);
        }
    }

    public function test_cube_inputs_are_rounded_to_two_decimals_before_calculation_and_storage(): void
    {
        $this->post(route('quality.tests.submit', $this->test), [
            'test_date' => now()->toDateString(),
            'concrete_specimens' => array_fill(0, 3, ['ident_mark' => 'Cube', 'weight_kg' => 8.2349, 'load_kn' => 550.126]),
        ])->assertSessionHasNoErrors();
        $meta = json_decode($this->test->measurements()->firstOrFail()->value_text, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('8.23', $meta['weight_kg']);
        $this->assertSame('550.13', $meta['load_kn']);
        $this->assertSame('24.45', $meta['strength_mpa']);
        $this->assertSame('2438.52', $meta['density']);
        $specimen = $this->test->sets()->firstOrFail()->specimens()->firstOrFail();
        $this->assertEqualsWithDelta(8.23, (float) $specimen->weight_kg, .0001);
        $this->assertEqualsWithDelta(550.13, (float) $specimen->load_kn, .0001);
    }

    public function test_can_view_tests_index()
    {
        $response = $this->get(route('quality.tests.index'));

        $response->assertStatus(200);
    }

    public function test_manual_test_creation_reuses_sample_and_opens_the_correct_age_for_execution()
    {
        QcTestParameter::factory()->create([
            'test_type_id' => $this->testType->id, 'name' => '15 Days Compressive Strength',
            'default_value' => null, 'target_value' => 25.5, 'min_value' => 23.0,
        ]);
        $sampleCount = QcSample::count();
        $this->post(route('quality.tests.store'), [
            'sample_id' => $this->sample->id, 'test_type_id' => $this->testType->id,
            'age_days' => 15, 'remarks' => 'Manual intermediate test',
        ])->assertSessionHasNoErrors();
        $test = QcTest::where('sample_id', $this->sample->id)->where('age_days', 15)->sole();
        $this->assertEquals($sampleCount, QcSample::count());
        $this->assertEquals(25.5, $test->target_strength);
        $this->assertEquals(23.0, $test->min_strength);
        $this->assertNull($test->test_date);
        $this->assertNull($test->tested_by);
        $this->assertEquals($this->sample->sample_date->copy()->addDays(15)->toDateString(), $test->scheduled_date->toDateString());
        $this->get(route('quality.tests.execute', $test))->assertInertia(fn ($page) => $page
            ->component('Quality/Tests/Execute')->where('test.age_days', 15)
            ->has('test.sets', 1)->where('test.sets.0.age_days', 15)->has('test.sets.0.specimens', 3));
        $this->post(route('quality.tests.submit', $test), [
            'test_date' => now()->toDateString(),
            'concrete_specimens' => [
                ['ident_mark' => '1', 'weight_kg' => 8.4, 'load_kn' => 600, 'strength_mpa' => 26.67],
                ['ident_mark' => '2', 'weight_kg' => 8.5, 'load_kn' => 610, 'strength_mpa' => 27.11],
                ['ident_mark' => '3', 'weight_kg' => 8.3, 'load_kn' => 590, 'strength_mpa' => 26.22],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertNotEquals('pending', $test->fresh()->overall_status);
        $this->assertEquals($this->user->id, $test->fresh()->tested_by);
    }

    public function test_manual_creation_rejects_duplicates_and_invalid_age_or_type()
    {
        $payload = ['sample_id' => $this->sample->id, 'test_type_id' => $this->testType->id, 'age_days' => 7];
        $this->postJson(route('quality.tests.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('age_days');
        $this->postJson(route('quality.tests.store'), array_replace($payload, ['age_days' => 8]))
            ->assertUnprocessable()->assertJsonValidationErrors('age_days');
        $this->testType->update(['is_active' => false]);
        $this->postJson(route('quality.tests.store'), array_replace($payload, ['age_days' => 15]))
            ->assertUnprocessable()->assertJsonValidationErrors('test_type_id');
        $this->assertEquals(1, QcTest::count());
    }

    public function test_manual_creation_and_execution_reject_foreign_plant_records()
    {
        $otherPlant = Plant::factory()->create();
        $sample = QcSample::factory()->create(['plant_id' => $otherPlant->id]);
        $type = QcTestType::factory()->create(['plant_id' => $otherPlant->id]);
        $payload = ['sample_id' => $sample->id, 'test_type_id' => $this->testType->id, 'age_days' => 15];
        $this->postJson(route('quality.tests.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('sample_id');
        $this->postJson(route('quality.tests.store'), array_replace($payload, ['sample_id' => $this->sample->id, 'test_type_id' => $type->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('test_type_id');
        $test = QcTest::factory()->create(['plant_id' => $otherPlant->id, 'sample_id' => $sample->id, 'test_type_id' => $type->id]);
        $this->get(route('quality.tests.execute', $test))->assertNotFound();
        $this->post(route('quality.tests.submit', $test), [])->assertNotFound();
    }

    public function test_approved_test_execution_cannot_be_overwritten()
    {
        $this->test->update(['approval_status' => 'approved', 'overall_status' => 'pass']);
        $this->postJson(route('quality.tests.submit', $this->test), ['test_date' => now()->toDateString()])->assertUnprocessable();
        $this->assertEquals('pass', $this->test->fresh()->overall_status);
    }

    public function test_can_view_tests_filtered_by_pending()
    {
        $response = $this->get(route('quality.tests.pending'));

        $response->assertRedirect(route('quality.tests.index', ['status' => 'pending']));
    }

    public function test_can_view_tests_filtered_by_completed()
    {
        $response = $this->get(route('quality.tests.completed'));

        $response->assertRedirect(route('quality.tests.index', ['status' => 'pass']));
    }

    public function test_can_view_tests_filtered_by_failed()
    {
        $response = $this->get(route('quality.tests.failed'));

        $response->assertRedirect(route('quality.tests.index', ['status' => 'fail']));
    }

    public function test_can_view_execute_form()
    {
        $response = $this->get(route('quality.tests.execute', $this->test));

        $response->assertStatus(200);
    }

    public function test_can_submit_concrete_specimen_execution()
    {
        // Create a parameter for the test type
        QcTestParameter::factory()->create([
            'test_type_id' => $this->testType->id,
            'name' => '7-day',
            'code' => '7_DAY',
            'default_value' => '7',
            'target_value' => 17.5,
            'min_value' => 15.0,
            'rule_type' => 'GREATER_THAN_OR_EQUAL',
        ]);

        $payload = [
            'test_date' => now()->toDateString(),
            'concrete_specimens' => [
                ['ident_mark' => '1', 'weight_kg' => 8.5, 'load_kn' => 450, 'strength_mpa' => 20.0],
                ['ident_mark' => '2', 'weight_kg' => 8.3, 'load_kn' => 440, 'strength_mpa' => 19.5],
                ['ident_mark' => '3', 'weight_kg' => 8.4, 'load_kn' => 460, 'strength_mpa' => 20.4],
            ],
        ];

        $response = $this->post(route('quality.tests.submit', $this->test), $payload);

        $response->assertRedirect();

        $this->test->refresh();
        $this->assertNotEquals('pending', $this->test->overall_status);
        $this->assertNotNull($this->test->evaluated_at);

        // Should have created measurements
        $this->assertGreaterThan(0, $this->test->measurements()->count());
    }

    public function test_can_approve_test()
    {
        $this->test->update(['overall_status' => 'pass', 'evaluated_at' => now()]);

        $response = $this->post(route('quality.tests.approve', $this->test));

        $response->assertStatus(302);

        $this->test->refresh();
        $this->assertEquals('approved', $this->test->approval_status);
        $this->assertNotNull($this->test->reviewed_at);
    }

    public function test_can_mark_test_for_retest()
    {
        $this->test->update(['overall_status' => 'fail']);

        $response = $this->post(route('quality.tests.retest', $this->test), [
            'retest_reason' => 'Specimen was damaged during testing',
        ]);

        $response->assertStatus(302);

        $this->test->refresh();
        $this->assertEquals('retest', $this->test->overall_status);
        $this->assertEquals('Specimen was damaged during testing', $this->test->retest_reason);
    }

    public function test_retest_requires_reason()
    {
        $response = $this->postJson(route('quality.tests.retest', $this->test), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['retest_reason']);
    }

    public function test_concrete_test_passes_when_avg_above_min_strength()
    {
        QcTestParameter::factory()->create([
            'test_type_id' => $this->testType->id,
            'name' => '7-day',
            'code' => '7_DAY',
            'default_value' => '7',
        ]);

        $payload = [
            'test_date' => now()->toDateString(),
            'concrete_specimens' => [
                ['ident_mark' => '1', 'weight_kg' => 8.35, 'load_kn' => 405, 'strength_mpa' => 18.0],
                ['ident_mark' => '2', 'weight_kg' => 8.35, 'load_kn' => 393.75, 'strength_mpa' => 17.5],
                ['ident_mark' => '3', 'weight_kg' => 8.35, 'load_kn' => 427.5, 'strength_mpa' => 19.0],
            ],
        ];

        $this->post(route('quality.tests.submit', $this->test), $payload);

        $this->test->refresh();
        // Average is 18.17 which is >= min_strength 15.0
        $this->assertEquals('pass', $this->test->overall_status);
    }

    public function test_concrete_test_fails_when_avg_below_min_strength()
    {
        $this->test->update(['min_strength' => 25.0]);

        QcTestParameter::factory()->create([
            'test_type_id' => $this->testType->id,
            'name' => '28-day',
            'code' => '28_DAY',
            'default_value' => '28',
        ]);

        $payload = [
            'test_date' => now()->toDateString(),
            'concrete_specimens' => [
                ['ident_mark' => '1', 'weight_kg' => 8.35, 'load_kn' => 450, 'strength_mpa' => 20.0],
                ['ident_mark' => '2', 'weight_kg' => 8.35, 'load_kn' => 495, 'strength_mpa' => 22.0],
                ['ident_mark' => '3', 'weight_kg' => 8.35, 'load_kn' => 472.5, 'strength_mpa' => 21.0],
            ],
        ];

        $this->post(route('quality.tests.submit', $this->test), $payload);

        $this->test->refresh();
        // Average is 21.0 which is < min_strength 25.0
        $this->assertEquals('fail', $this->test->overall_status);
    }

    public static function referenceCertificateData(): array
    {
        return [
            '11 September' => [11, '2026-10-09', [8.380, 8.420, 8.360], [690, 750, 720], [30.67, 33.33, 32.00], [2483, 2495, 2477], 32.00],
            '12 September' => [12, '2026-10-10', [8.350, 8.290, 8.370], [730, 810, 740], [32.44, 36.00, 32.89], [2474, 2456, 2480], 33.78],
            '13 September' => [13, '2026-10-11', [8.330, 8.390, 8.350], [680, 710, 680], [30.22, 31.56, 30.22], [2468, 2486, 2474], 30.67],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('referenceCertificateData')]
    public function test_reference_certificate_calculations_and_pdf(int $day, string $testedOn, array $weights, array $loads, array $strengths, array $densities, float $average): void
    {
        $this->sample->concreteGrade->update(['name' => 'M30']);
        $this->sample->update([
            'sample_date' => sprintf('2026-09-%02d', $day), 'specimen_size' => '150x150x150 mm',
            'slump_mm' => 120, 'source_location' => 'SITE', 'site_name' => 'SIPCOT POCHAMPALLI',
            'customer_id' => \App\Models\Patron::factory()->create(['plant_id' => $this->plant->id, 'legal_name' => 'M N CONTRACTER'])->id,
        ]);
        $this->plant->update(['name' => 'M N S CONCRETE']);
        $this->user->update(['username' => 'QC Technician (test fixture)']);
        $this->test->update(['age_days' => 28, 'min_strength' => 30, 'target_strength' => 30, 'test_no' => 'REF-'.$day.'-28D']);
        $specimens = array_map(fn ($index) => [
            'ident_mark' => (string) ($index + 1), 'weight_kg' => $weights[$index], 'load_kn' => $loads[$index],
            'strength_mpa' => 999, 'density' => 999,
        ], [0, 1, 2]);
        $this->post(route('quality.tests.submit', $this->test), [
            'test_date' => $testedOn, 'concrete_specimens' => $specimens, 'avg_strength' => 999, 'overall_status' => 'fail',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $test = $this->test->fresh(['sets.specimens', 'results', 'measurements']);
        $this->assertSame('pass', $test->overall_status);
        $set = $test->sets->sole();
        $this->assertEquals($average, (float) $set->average_strength);
        $this->assertEquals(28, $set->age_days);
        $this->assertEquals($average, (float) $test->results->sole()->final_value);
        foreach ($set->specimens as $index => $specimen) {
            $this->assertEquals($strengths[$index], (float) $specimen->strength_mpa);
            $this->assertEquals($densities[$index], round((float) $specimen->density_kg_m3));
            $this->assertEquals(22500, (float) $specimen->cross_sectional_area);
        }
        $sets = app(\App\Services\QC\CubeReportDataService::class)->certificateSets($test);
        $this->assertCount(1, $sets);
        $this->assertEquals(150, $sets->sole()->dimensions['length_mm']);
        $response = $this->get(route('quality.tests.cube-report-pdf', $test));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        if ($directory = getenv('QC_REPORT_OUTPUT_DIR')) {
            file_put_contents($directory.'/concrete-strength-2026-09-'.$day.'-28d.pdf', $response->getContent());
        }
    }

    public function test_cube_geometry_and_snapshot_are_used_instead_of_browser_calculations(): void
    {
        $this->sample->update(['specimen_size' => '100x100x100 mm']);
        $payload = ['test_date' => now()->toDateString(), 'concrete_specimens' => array_fill(0, 3, ['weight_kg' => 2.4, 'load_kn' => 300, 'strength_mpa' => 999])];
        $this->post(route('quality.tests.submit', $this->test), $payload)->assertSessionHasNoErrors();
        $specimen = $this->test->fresh()->specimens()->first();
        $this->assertEquals(30, (float) $specimen->strength_mpa);
        $this->assertEquals(2400, (float) $specimen->density_kg_m3);
        $this->sample->update(['specimen_size' => '150x150x150 mm']);
        $dimensions = app(\App\Services\QC\ConcreteCubeCalculator::class)->dimensions($this->test->fresh());
        $this->assertEquals(10000, $dimensions['area_mm2']);
    }

    public function test_incomplete_negative_and_backdated_cube_submissions_are_rejected(): void
    {
        $this->get(route('quality.tests.cube-report-pdf', $this->test))->assertUnprocessable();
        $payload = ['test_date' => now()->toDateString(), 'concrete_specimens' => array_fill(0, 3, ['weight_kg' => 8.3, 'load_kn' => 680])];
        $partial = $payload;
        unset($partial['concrete_specimens'][1]['load_kn']);
        $this->postJson(route('quality.tests.submit', $this->test), $partial)->assertUnprocessable();
        $partial['concrete_specimens'][1]['load_kn'] = -1;
        $this->postJson(route('quality.tests.submit', $this->test), $partial)->assertUnprocessable();
        $payload['test_date'] = $this->sample->sample_date->copy()->subDay()->toDateString();
        $this->postJson(route('quality.tests.submit', $this->test), $payload)->assertUnprocessable();
        $this->assertEquals(0, $this->test->measurements()->count());
        $this->assertSame('pending', $this->test->fresh()->overall_status);
    }

    public function test_partial_sets_stay_pending_until_all_specimens_are_recorded(): void
    {
        $this->test->ensureSetsAndSpecimensInitialized();
        $set = $this->test->sets()->first();
        $specimens = $set->specimens()->get();
        $payload = ['test_date' => now()->toDateString(), 'sets' => [[
            'id' => $set->id, 'specimens' => [['id' => $specimens[0]->id, 'weight_kg' => 8.35, 'load_kn' => 680]],
        ]]];
        $this->post(route('quality.tests.submit', $this->test), $payload)->assertSessionHasNoErrors();
        $this->assertSame('pending', $this->test->fresh()->overall_status);
        $this->assertNull($set->fresh()->average_strength);
        $this->assertSame('in_progress', $this->sample->fresh()->status);
        $this->get(route('quality.tests.cube-report-pdf', $this->test))->assertUnprocessable();
        $payload['sets'][0]['specimens'] = $specimens->skip(1)->map(fn ($specimen) => ['id' => $specimen->id, 'weight_kg' => 8.35, 'load_kn' => 680])->values()->all();
        $this->post(route('quality.tests.submit', $this->test), $payload)->assertSessionHasNoErrors();
        $this->assertSame('pass', $this->test->fresh()->overall_status);
        $this->assertEquals(30.22, (float) $set->fresh()->average_strength);
    }

    public function test_foreign_sets_and_report_records_are_not_accessible(): void
    {
        $other = QcTest::factory()->create(['plant_id' => $this->plant->id, 'sample_id' => $this->sample->id, 'test_type_id' => $this->testType->id, 'age_days' => 28]);
        $other->ensureSetsAndSpecimensInitialized();
        $this->postJson(route('quality.tests.submit', $this->test), ['sets' => [['id' => $other->sets()->first()->id]]])
            ->assertUnprocessable()->assertJsonValidationErrors('sets.0.id');
        $this->test->ensureSetsAndSpecimensInitialized();
        $ownSet = $this->test->sets()->first();
        $this->postJson(route('quality.tests.submit', $this->test), ['sets' => [[
            'id' => $ownSet->id, 'specimens' => [[
                'id' => $other->specimens()->first()->id, 'weight_kg' => 8.35, 'load_kn' => 680,
            ]],
        ]]])->assertUnprocessable();
        $this->assertNull($other->specimens()->first()->weight_kg);
        $other->update(['plant_id' => Plant::factory()->create()->id]);
        $this->get(route('quality.tests.cube-report-pdf', $other))->assertNotFound();
    }

    public function test_outlier_results_require_retest_even_when_browser_requests_pass(): void
    {
        $this->post(route('quality.tests.submit', $this->test), [
            'test_date' => now()->toDateString(), 'overall_status' => 'pass',
            'concrete_specimens' => array_map(fn ($load) => ['weight_kg' => 8.35, 'load_kn' => $load], [400, 400, 900]),
        ])->assertSessionHasNoErrors();
        $this->assertSame('retest', $this->test->fresh()->overall_status);
        $this->assertSame('NONE', $this->test->results()->sole()->status);
        $this->assertTrue($this->test->results()->sole()->criteria_snapshot['has_outlier']);
    }

    public function test_certificate_uses_selected_test_while_worksheet_keeps_completed_ages(): void
    {
        $payload = ['test_date' => now()->toDateString(), 'concrete_specimens' => array_fill(0, 3, ['weight_kg' => 8.35, 'load_kn' => 680])];
        $this->post(route('quality.tests.submit', $this->test), $payload)->assertSessionHasNoErrors();
        $other = QcTest::factory()->create([
            'plant_id' => $this->plant->id, 'sample_id' => $this->sample->id, 'test_type_id' => $this->testType->id,
            'age_days' => 28, 'test_no' => 'LATER-28D',
        ]);
        $this->post(route('quality.tests.submit', $other), $payload)->assertSessionHasNoErrors();
        $service = app(\App\Services\QC\CubeReportDataService::class);
        $this->assertCount(1, $service->certificateSets($this->test->fresh()));
        $this->assertCount(2, $service->sampleSets($this->test->fresh()));
        $this->get(route('quality.tests.cube-report-pdf', [$this->test, 'format' => 'worksheet']))->assertOk();

        // Historical JSON observations remain printable without migrating them.
        $this->test->sets()->forceDelete();
        $legacy = $service->certificateSets($this->test->fresh());
        $this->assertCount(1, $legacy);
        $this->assertEquals(30.22, $legacy->sole()->average_strength);
        $this->assertEquals(2474, round($legacy->sole()->specimens->first()->density_kg_m3));
    }

    public function test_search_filters_tests_by_test_no()
    {
        $response = $this->get(route('quality.tests.index', ['search' => $this->test->test_no]));

        $response->assertStatus(200);
    }
}
