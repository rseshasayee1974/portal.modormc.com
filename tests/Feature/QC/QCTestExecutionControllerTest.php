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
        $this->user->update(['default_plant_id' => $this->plant->id]);
        $this->actingAs($this->user);

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

    public function test_can_view_tests_index()
    {
        $response = $this->get(route('quality.tests.index'));

        $response->assertStatus(200);
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
        $this->test->update(['overall_status' => 'pass']);

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
                ['ident_mark' => '1', 'strength_mpa' => 18.0],
                ['ident_mark' => '2', 'strength_mpa' => 17.5],
                ['ident_mark' => '3', 'strength_mpa' => 19.0],
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
                ['ident_mark' => '1', 'strength_mpa' => 20.0],
                ['ident_mark' => '2', 'strength_mpa' => 22.0],
                ['ident_mark' => '3', 'strength_mpa' => 21.0],
            ],
        ];

        $this->post(route('quality.tests.submit', $this->test), $payload);

        $this->test->refresh();
        // Average is 21.0 which is < min_strength 25.0
        $this->assertEquals('fail', $this->test->overall_status);
    }

    public function test_search_filters_tests_by_test_no()
    {
        $response = $this->get(route('quality.tests.index', ['search' => $this->test->test_no]));

        $response->assertStatus(200);
    }
}
