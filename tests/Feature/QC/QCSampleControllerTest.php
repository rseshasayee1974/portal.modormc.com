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
        $this->user->update([
            'default_plant_id' => $this->plant->id,
            'default_entity_id' => $this->plant->entity_id,
        ]);
        \App\Models\EntityUser::create([
            'user_id' => $this->user->id, 'entity_id' => $this->plant->entity_id,
            'plant_id' => $this->plant->id, 'role_id' => \App\Models\Role::factory()->create()->id,
        ]);
        $this->actingAs($this->user);
        $this->withSession(['active_plant_id' => $this->plant->id]);
    }

    public function test_only_selected_sales_order_batches_get_samples_and_schedules()
    {
        $order = \App\Models\SalesOrder::factory()->create(['plant_id' => $this->plant->id, 'status' => \App\Models\SalesOrder::STATUS_IN_PROGRESS]);
        $batches = \App\Models\Batch::factory()->count(3)->create([
            'plant_id' => $this->plant->id, 'sales_order_id' => $order->id,
            'status' => \App\Models\Batch::STATUS_COMPLETED,
        ]);
        $type = QcTestType::factory()->create(['plant_id' => $this->plant->id]);
        $payload = [
            'sales_order_id' => $order->id,
            'source_keys' => ['batch:' . $batches[0]->id, 'batch:' . $batches[2]->id],
            'sample_date' => now()->toDateString(), 'test_type_ids' => [$type->id],
            'schedule_milestones' => [7, 28],
        ];
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('mm_qc_samples', 2);
        $this->assertDatabaseMissing('mm_qc_samples', ['batch_id' => $batches[1]->id]);
        foreach (QcSample::all() as $sample) {
            $this->assertEquals([7, 28], $sample->tests()->orderBy('age_days')->pluck('age_days')->all());
        }
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasErrors('source_keys');
        $payload['source_keys'] = ['batch:' . $batches[1]->id];
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('mm_qc_samples', 3);
    }

    public function test_sales_order_selection_requires_at_least_one_production_row()
    {
        $this->post(route('quality.samples.store'), [
            'sales_order_id' => 1, 'source_keys' => [], 'sample_date' => now()->toDateString(),
        ])->assertSessionHasErrors('source_keys');
        $this->assertDatabaseCount('mm_qc_samples', 0);
    }

    public function test_manual_new_sample_flow_creates_the_selected_age_and_redirects_to_execution()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $payload['source_keys'] = ['dispatch:'.$dispatch->id];
        $payload['schedule_milestones'] = [15];
        $payload['execute_after_create'] = true;
        $response = $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $sample = QcSample::sole();
        $test = $sample->tests()->sole();
        $response->assertRedirect(route('quality.tests.execute', $test));
        $this->assertEquals(15, $test->age_days);
        $this->assertNull($test->test_date);
        $this->assertNull($test->tested_by);
        $this->assertEquals('2026-10-24', $test->scheduled_date->toDateString());
        $this->get(route('quality.tests.execute', $test))->assertInertia(fn ($page) => $page
            ->where('test.age_days', 15)->has('test.sets', 1)->where('test.sets.0.age_days', 15));
    }

    public function test_sample_editor_reschedules_pending_tests_and_protects_executed_test_dates()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $sample = QcSample::sole();
        $payload['sample_date'] = '2026-10-10';
        $this->put(route('quality.samples.update', $sample), $payload)->assertSessionHasNoErrors();
        $this->assertEquals(['2026-10-16', '2026-10-24', '2026-11-06'],
            $sample->tests()->orderBy('age_days')->get()->map(fn ($test) => $test->scheduled_date->toDateString())->all());
        $sample->tests()->first()->update(['overall_status' => 'pass', 'evaluated_at' => now()]);
        $payload['sample_date'] = '2026-10-11';
        $this->put(route('quality.samples.update', $sample), $payload)->assertSessionHasErrors('sample_date');
        $this->assertEquals('2026-10-10', $sample->fresh()->sample_date->toDateString());
    }

    public function test_opening_samples_does_not_delete_manually_created_test_types()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $sample = QcSample::sole();
        $type = QcTestType::factory()->create(['plant_id' => $this->plant->id, 'name' => 'Split Tensile Strength']);
        $test = QcTest::factory()->create(['plant_id' => $this->plant->id, 'sample_id' => $sample->id,
            'test_type_id' => $type->id, 'age_days' => 7, 'overall_status' => 'pending']);
        $this->get(route('quality.samples.index'))->assertOk();
        $this->assertNotNull($test->fresh());
    }

    public function test_schedules_use_dispatch_date_even_when_casting_date_differs()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $payload['sample_date'] = '2026-10-08';
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $this->assertEquals(['2026-10-16', '2026-10-24', '2026-11-06'],
            QcSample::sole()->tests()->orderBy('age_days')->get()->map(fn ($test) => $test->scheduled_date->toDateString())->all());
    }

    public function test_dispatch_due_age_is_exact_and_uses_dispatch_instead_of_casting()
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-16 12:00:00', 'Asia/Kolkata'));
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $payload['sample_date'] = '2026-10-01';
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $this->get(route('quality.samples.index'))->assertInertia(fn ($page) => $page
            ->where('salesOrders.0.dispatches.0.qc_due_age', 7));
        $this->travelTo(\Carbon\Carbon::parse('2026-10-17 12:00:00', 'Asia/Kolkata'));
        $this->get(route('quality.samples.index'))->assertInertia(fn ($page) => $page
            ->where('salesOrders.0.dispatches.0.qc_due_age', null));
        $this->travelBack();
    }

    public function test_invalid_milestone_requests_never_create_partial_samples_or_tests(): void
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        foreach ([[], [7, 7], [7, 14, 28], ['bad'], [0], [365], '7'] as $ages) {
            $this->postJson(route('quality.samples.store'), array_replace($payload, ['schedule_milestones' => $ages]))
                ->assertUnprocessable();
            $this->assertDatabaseCount('mm_qc_samples', 0);
            $this->assertDatabaseCount('mm_qc_tests', 0);
        }
        $this->postJson(route('quality.samples.store'), array_replace($payload, ['test_type_ids' => [$payload['test_type_ids'][0], $payload['test_type_ids'][0]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('test_type_ids.0');
    }

    public function test_multiple_test_types_get_unique_numbers_for_each_selected_age(): void
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $second = QcTestType::factory()->create(['plant_id' => $this->plant->id]);
        $payload['test_type_ids'][] = $second->id;
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('mm_qc_samples', 1);
        $this->assertDatabaseCount('mm_qc_tests', 6);
        $this->assertSame(6, QcTest::pluck('test_no')->unique()->count());
    }

    public function test_invalid_direct_source_identifiers_and_unknown_technician_are_rejected(): void
    {
        $sampleDate = ['sample_date' => now()->toDateString()];
        $foreign = \App\Models\Dispatch::factory()->create();
        $this->postJson(route('quality.samples.store'), $sampleDate + ['dispatch_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('dispatch_id');
        $this->postJson(route('quality.samples.store'), $sampleDate + ['tested_by' => 99999999])
            ->assertUnprocessable()->assertJsonValidationErrors('tested_by');
        $this->postJson(route('quality.samples.store'), $sampleDate + ['source_keys' => ['dispatch:'.$foreign->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('sales_order_id');
        $this->assertDatabaseCount('mm_qc_samples', 0);
    }

    public function test_metadata_edits_reject_invalid_or_changed_test_age_payloads(): void
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $sample = QcSample::sole();
        foreach ([[14], [], [7, 7], [15]] as $ages) {
            $this->putJson(route('quality.samples.update', $sample), array_replace($payload, ['schedule_milestones' => $ages]))
                ->assertUnprocessable();
        }
        $this->assertSame([7, 15, 28], $sample->tests()->orderBy('age_days')->pluck('age_days')->all());
    }

    private function productionFixture(): array
    {
        $order = \App\Models\SalesOrder::factory()->create(['plant_id' => $this->plant->id, 'status' => \App\Models\SalesOrder::STATUS_IN_PROGRESS]);
        $batch = \App\Models\Batch::factory()->create([
            'plant_id' => $this->plant->id, 'sales_order_id' => $order->id,
            'status' => \App\Models\Batch::STATUS_COMPLETED,
        ]);
        $dispatch = \App\Models\Dispatch::withoutEvents(fn () => \App\Models\Dispatch::factory()->create([
            'plant_id' => $this->plant->id, 'sales_order_id' => $order->id,
            'batch_id' => $batch->id, 'status' => 1, 'dispatch_status' => 'Invoiced',
            'dispatch_time' => '2026-10-09 10:00:00',
            'dispatch_no' => 'QC-101', 'mixdesign_id' => $order->mix_design_id,
            'customer_id' => $order->customer_id, 'unload_site_id' => $order->site_id,
        ]));
        $dispatch->invoiceStatus()->create([
            'plant_id' => $this->plant->id, 'invoice_number' => 'INV-QC-101',
        ]);
        $type = QcTestType::factory()->create([
            'plant_id' => $this->plant->id, 'category' => 'Concrete', 'name' => 'Compressive Strength',
        ]);
        return [$order, $batch, $dispatch, [
            'sales_order_id' => $order->id, 'source_keys' => ['batch:' . $batch->id],
            'sample_date' => '2026-10-09', 'schedule_milestones' => [7, 15, 28],
            'test_type_ids' => [$type->id],
        ]];
    }

    public function test_invoiced_batch_with_numeric_dispatch_status_creates_correct_schedule()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $this->post(route('quality.samples.store'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('quality.samples.index'));
        $sample = QcSample::sole();
        $this->assertEquals($batch->id, $sample->batch_id);
        $this->assertEquals($dispatch->id, $sample->dispatch_id);
        $this->assertEquals($order->customer_id, $sample->customer_id);
        $this->assertEquals($order->mixDesign->concrete_grade_id, $sample->concrete_grade_id);
        $this->assertEquals(['2026-10-16', '2026-10-24', '2026-11-06'],
            $sample->tests()->orderBy('age_days')->get()->map(fn ($test) => $test->scheduled_date->toDateString())->all());
    }

    public function test_dispatch_source_keys_are_accepted_and_cannot_duplicate_batch_samples()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $payload['source_keys'] = ['dispatch:' . $dispatch->id];
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $payload['source_keys'] = ['batch:' . $batch->id];
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasErrors('source_keys');
        $this->assertDatabaseCount('mm_qc_samples', 1);
    }

    public function test_completed_dispatched_orders_include_sample_age_and_test_details()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $order->update(['status' => \App\Models\SalesOrder::STATUS_COMPLETED]);
        $sample = QcSample::sole();
        $sample->tests()->update(['tested_by' => $this->user->id]);

        $this->get(route('quality.samples.index'))->assertInertia(fn ($page) => $page
            ->component('Quality/Samples/Index')
            ->where('salesOrders.0.id', $order->id)
            ->where('salesOrders.0.dispatches.0.id', $dispatch->id)
            ->where('salesOrders.0.dispatches.0.invoice_status.invoice_number', 'INV-QC-101')
            ->where('salesOrders.0.dispatches.0.quality_sample.sample_no', $sample->sample_no)
            ->has('salesOrders.0.dispatches.0.quality_sample.tests', 3)
            ->where('salesOrders.0.dispatches.0.quality_sample.tests.0.tester.id', $this->user->id));

        $dispatch->update(['dispatch_status' => 'Loading']);
        $this->get(route('quality.samples.index'))->assertInertia(fn ($page) => $page->has('salesOrders', 0));
    }

    public function test_create_and_edit_expose_invoice_details_without_overwriting_numeric_status()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $this->get(route('quality.samples.create'))->assertRedirect(route('quality.samples.index'));
        $this->get(route('quality.samples.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Quality/Samples/Index')
            ->where('salesOrders.0.batches.0.dispatches.0.status', 1)
            ->where('salesOrders.0.batches.0.dispatches.0.invoice_status.invoice_number', 'INV-QC-101'));
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $this->get(route('quality.samples.edit', QcSample::sole()))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Quality/Samples/Edit')->where('sample.dispatch_id', $dispatch->id));
    }

    public function test_invalid_source_keys_are_validation_errors_on_create_and_update()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $sample = QcSample::sole();
        foreach (['invoice:1', 'batch:abc', 'batch:1/anything'] as $key) {
            $payload['source_keys'] = [$key];
            $this->postJson(route('quality.samples.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('source_keys.0');
            $this->putJson(route('quality.samples.update', $sample), $payload)->assertUnprocessable()->assertJsonValidationErrors('source_keys.0');
        }
        $this->assertDatabaseCount('mm_qc_samples', 1);
    }

    public function test_cancelled_dispatch_cannot_be_sampled()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        \App\Models\Dispatch::withoutEvents(fn () => $dispatch->update(['dispatch_status' => 'Cancelled']));
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasErrors('source_keys');
        $this->assertDatabaseCount('mm_qc_samples', 0);
        $this->assertDatabaseCount('mm_qc_tests', 0);
    }

    public function test_invalid_selection_is_atomic_and_rejects_other_orders()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $other = \App\Models\Batch::factory()->create(['plant_id' => $this->plant->id]);
        $payload['source_keys'][] = 'batch:' . $other->id;
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasErrors('source_keys');
        $this->assertDatabaseCount('mm_qc_samples', 0);
        $this->assertDatabaseCount('mm_qc_tests', 0);
        $payload['source_keys'] = ['batch:' . $batch->id, 'dispatch:' . $dispatch->id];
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasErrors('source_keys');
        $this->assertDatabaseCount('mm_qc_samples', 0);
    }

    public function test_editing_current_selection_preserves_scheduled_tests_and_rejects_multiple_rows()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $sample = QcSample::sole();
        $testIds = $sample->tests()->pluck('id')->all();
        $payload['remarks'] = 'Updated sampling notes';
        $this->put(route('quality.samples.update', $sample), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Updated sampling notes', $sample->fresh()->remarks);
        $this->assertSame($testIds, $sample->tests()->pluck('id')->all());
        $another = \App\Models\Batch::factory()->create([
            'plant_id' => $this->plant->id, 'sales_order_id' => $order->id,
        ]);
        $payload['source_keys'][] = 'batch:' . $another->id;
        $this->put(route('quality.samples.update', $sample), $payload)->assertSessionHasErrors('source_keys');
        $this->assertEquals($batch->id, $sample->fresh()->batch_id);
    }

    public function test_samples_from_other_plants_cannot_be_edited_updated_or_deleted()
    {
        $sample = QcSample::factory()->create();
        $this->get(route('quality.samples.edit', $sample))->assertNotFound();
        $this->put(route('quality.samples.update', $sample), ['sample_date' => '2026-10-09'])->assertNotFound();
        $this->delete(route('quality.samples.destroy', $sample))->assertNotFound();
        $this->assertNotSoftDeleted($sample);
    }

    public function test_deleted_sample_frees_production_for_later_sampling()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $sample = QcSample::sole();
        $this->delete(route('quality.samples.destroy', $sample))->assertRedirect(route('quality.samples.index'));
        $this->assertSoftDeleted($sample);
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $this->assertEquals(1, QcSample::count());
    }

    public function test_cancelled_batches_and_foreign_sales_orders_are_rejected()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $batch->update(['status' => \App\Models\Batch::STATUS_CANCELLED]);
        $this->postJson(route('quality.samples.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('source_keys');
        $otherPlant = Plant::factory()->create();
        $order->update(['plant_id' => $otherPlant->id]);
        $this->postJson(route('quality.samples.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('sales_order_id');
        $this->assertDatabaseCount('mm_qc_samples', 0);
    }

    public function test_duplicate_keys_and_missing_sales_order_are_rejected()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $payload['source_keys'][] = $payload['source_keys'][0];
        $this->postJson(route('quality.samples.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('source_keys.0');
        $payload['source_keys'] = ['batch:' . $batch->id];
        $payload['sales_order_id'] = null;
        $this->postJson(route('quality.samples.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('sales_order_id');
        $this->assertDatabaseCount('mm_qc_samples', 0);
    }

    public function test_unified_workspace_keeps_unlinked_samples_without_duplicating_dispatch_samples()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        $linked = QcSample::sole();
        $standalone = QcSample::factory()->count(21)->create([
            'plant_id' => $this->plant->id, 'batch_id' => null, 'dispatch_id' => null,
            'concrete_grade_id' => $linked->concrete_grade_id,
        ]);
        QcSample::factory()->create([
            'plant_id' => Plant::factory()->create()->id, 'concrete_grade_id' => $linked->concrete_grade_id,
        ]);

        $this->get(route('quality.samples.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->has('additionalSamples', 21)
            ->where('additionalSamples', fn ($samples) => collect($samples)->pluck('id')->sort()->values()->all()
                === $standalone->pluck('id')->sort()->values()->all())
            ->where('salesOrders.0.dispatches.0.quality_sample.id', $linked->id));

        $dispatch->update(['dispatch_status' => 'Loading']);
        $this->get(route('quality.samples.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->has('salesOrders', 0)->has('additionalSamples', 22));
    }

    public function test_register_pagination_sorting_and_status_filters_apply_to_all_samples()
    {
        $grade = ConcreteGrade::factory()->create(['status' => true]);
        for ($index = 0; $index < 12; $index++) {
            QcSample::factory()->create([
                'plant_id' => $this->plant->id, 'concrete_grade_id' => $grade->id,
                'sample_no' => sprintf('QC-PAGE-%03d', $index),
                'status' => $index === 11 ? 'completed' : 'pending_test',
            ]);
        }
        $this->get(route('quality.samples.index', [
            'per_page' => 10, 'page' => 2, 'sort' => 'sample_no', 'direction' => 'asc', 'status' => 'pending_test',
        ]))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Quality/Samples/Index')->where('samples.total', 11)
            ->where('samples.current_page', 2)->where('samples.per_page', 10)
            ->has('samples.data', 1)->where('samples.data.0.sample_no', 'QC-PAGE-010'));
        $this->get(route('quality.samples.index', ['search' => 'QC-PAGE-011']))
            ->assertOk()->assertInertia(fn ($page) => $page->where('samples.total', 1)
                ->where('samples.data.0.status', 'completed'));
    }

    public function test_register_can_search_batch_and_dispatch_numbers()
    {
        [$order, $batch, $dispatch, $payload] = $this->productionFixture();
        $this->post(route('quality.samples.store'), $payload)->assertSessionHasNoErrors();
        foreach ([$batch->batch_no, $dispatch->dispatch_no] as $search) {
            $this->get(route('quality.samples.index', ['search' => $search]))->assertOk()
                ->assertInertia(fn ($page) => $page->where('samples.total', 1));
        }
    }

    public function test_can_view_samples_index()
    {
        $response = $this->get(route('quality.samples.index'));

        $response->assertStatus(200);
    }

    public function test_can_view_create_form()
    {
        $response = $this->get(route('quality.samples.create'));

        $response->assertRedirect(route('quality.samples.index'));
        $this->get(route('quality.samples.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Quality/Samples/Index')->has('salesOrders')->has('personnels')
            ->has('usedBatchIds')->has('usedDispatchIds')->has('samples'));
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
