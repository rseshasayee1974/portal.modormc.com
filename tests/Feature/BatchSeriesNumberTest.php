<?php
/*
Author: ragul-onemodo
Created: 2026-11-02 17:10:42 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\Batch;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

class BatchSeriesNumberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['active_plant_id' => 1]);
        config(['database.default' => 'batch_series_test', 'database.connections.batch_series_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Schema::create('mm_plants', fn (Blueprint $table) => $table->id());
        DB::table('mm_plants')->insert([['id' => 1], ['id' => 2]]);
        Schema::create('mm_batches', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->string('batch_no')->nullable(); $table->softDeletes();
            $table->string('active_batch_no')->nullable()->virtualAs('CASE WHEN deleted_at IS NULL THEN batch_no ELSE NULL END');
            $table->unique(['plant_id', 'active_batch_no'], 'mm_batches_plant_active_batch_no_unique');
        });
        DB::table('mm_batches')->insert(['plant_id' => 1, 'batch_no' => 29]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_selected_series_has_its_own_counter_in_the_same_batch_number_column(): void
    {
        foreach (['B30', 'P1', 'B31', 'P2', 'B32'] as $expected) {
            DB::transaction(function () use ($expected) {
                $next = Batch::nextSeriesNumber(1, $expected[0]);
                $this->assertSame($expected, $next['batch_no']);
                DB::table('mm_batches')->insert(['plant_id' => 1, 'batch_no' => $next['batch_no']]);
            });
        }
        $this->assertSame(['29', 'B30', 'P1', 'B31', 'P2', 'B32'], DB::table('mm_batches')->orderBy('id')->pluck('batch_no')->all());
        $this->assertSame(['id','plant_id','batch_no','deleted_at','active_batch_no'], Schema::getColumnListing('mm_batches'));
    }

    public function test_counters_are_numeric_plant_scoped_and_reserve_deleted_numbers(): void
    {
        DB::table('mm_batches')->insert([
            ['plant_id' => 1, 'batch_no' => 'B39', 'deleted_at' => null],
            ['plant_id' => 1, 'batch_no' => 'B40', 'deleted_at' => null],
            ['plant_id' => 1, 'batch_no' => 'P15', 'deleted_at' => '2026-11-02'],
            ['plant_id' => 2, 'batch_no' => 'B99', 'deleted_at' => null],
        ]);
        $this->assertSame('B41', Batch::nextSeriesNumber(1, 'B')['batch_no']);
        $this->assertSame('P16', Batch::nextSeriesNumber(1, 'P')['batch_no']);
        $this->assertSame('B100', Batch::nextSeriesNumber(2, 'B')['batch_no']);
        $this->assertSame('P1', Batch::nextSeriesNumber(2, 'P')['batch_no']);
    }

    public function test_series_does_not_reset_or_add_financial_year(): void
    {
        DB::table('mm_batches')->insert(['plant_id' => 1, 'batch_no' => 'B42']);
        Carbon::setTestNow('2026-11-02');
        $this->assertSame('B43', Batch::nextSeriesNumber(1, 'B')['batch_no']);
        Carbon::setTestNow('2027-04-01');
        $this->assertSame('B43', Batch::nextSeriesNumber(1, 'B')['batch_no']);
    }

    public function test_generated_unique_key_preserves_distinct_series_and_blocks_duplicates(): void
    {
        DB::table('mm_batches')->insert([['plant_id' => 1, 'batch_no' => 'B1'], ['plant_id' => 1, 'batch_no' => 'P1']]);
        $this->assertSame('29', DB::table('mm_batches')->where('id', 1)->value('batch_no'));
        $this->expectException(QueryException::class);
        DB::table('mm_batches')->insert(['plant_id' => 1, 'batch_no' => 'B1']);
    }

    public function test_invalid_series_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        Batch::nextSeriesNumber(1, 'OTHER');
    }

    public function test_dispatch_prefix_and_number_match_the_selected_batch_number(): void
    {
        foreach (['B1', 'B12', 'P1', 'P25'] as $number) {
            $batch = new Batch(['plant_id' => 1, 'batch_no' => $number]);
            $details = \App\Models\Dispatch::nextDocumentNumber(1, null, $batch);
            $this->assertSame($number[0], $details['prefix']);
            $this->assertSame(substr($number, 1), $details['nextNumber']);
            $this->assertSame($number, $details['prefix'].$details['nextNumber']);
        }
    }

    public function test_dispatch_preview_reads_the_linked_batch_number(): void
    {
        $id = DB::table('mm_batches')->insertGetId(['plant_id' => 1, 'batch_no' => 'P12']);
        $request = \Illuminate\Http\Request::create('/', 'GET', ['batch_id' => $id]);
        $response = (new \App\Http\Controllers\DispatchController)->dropdowns($request);
        $this->assertSame(['prefix' => 'P', 'nextDispatchNo' => '12'], $response->getData(true));
    }

    public function test_dispatch_preview_rejects_a_batch_from_another_plant(): void
    {
        $id = DB::table('mm_batches')->insertGetId(['plant_id' => 2, 'batch_no' => 'P12']);
        $request = \Illuminate\Http\Request::create('/', 'GET', ['batch_id' => $id]);
        $this->expectException(ValidationException::class);
        (new \App\Http\Controllers\DispatchController)->dropdowns($request);
    }

    public function test_updates_accept_bp_numbers_and_still_block_unauthorized_renumbering(): void
    {
        Schema::create('mm_custom_settings', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->string('module_name'); $table->json('settings'); $table->softDeletes();
        });
        $id = DB::table('mm_batches')->insertGetId(['plant_id' => 1, 'batch_no' => 'B1']);
        $batch = Batch::withoutGlobalScopes()->find($id);
        foreach (['B1' => true, 'P1' => false] as $number => $valid) {
            $request = \App\Http\Requests\UpdateBatchRequest::create('/batches/'.$id, 'PUT', ['batch_no' => $number]);
            $route = new \Illuminate\Routing\Route('PUT', 'batches/{batch}', fn () => null);
            $route->bind($request);
            $route->setParameter('batch', $batch);
            $request->setRouteResolver(fn () => $route);
            $request->setUserResolver(fn () => null);
            $validator = \Illuminate\Support\Facades\Validator::make(['batch_no' => $number], ['batch_no' => $request->rules()['batch_no']]);
            $request->withValidator($validator);
            $this->assertSame($valid, $validator->passes());
        }
    }

    public function test_default_numbers_have_no_prefix_and_continue_existing_counters(): void
    {
        $this->assertSame('30', Batch::nextSeriesNumber(1)['batch_no']);
        DB::table('mm_batches')->insert([
            ['plant_id' => 1, 'batch_no' => 'B40', 'deleted_at' => null],
            ['plant_id' => 1, 'batch_no' => 'P50', 'deleted_at' => '2026-11-02'],
        ]);
        $this->assertSame('51', Batch::nextSeriesNumber(1)['batch_no']);
        $this->assertSame('', Batch::nextSeriesNumber(1)['series']);
        $this->assertSame('1', Batch::nextSeriesNumber(2)['batch_no']);
    }

    private function configureSeries(array $settings): void
    {
        Schema::create('mm_custom_settings', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->integer('module_id')->default(0);
            $table->string('module_name'); $table->json('settings'); $table->timestamps(); $table->softDeletes();
        });
        DB::table('mm_custom_settings')->insert(['plant_id' => 1, 'module_name' => 'batching', 'settings' => json_encode($settings)]);
    }

    public function test_batch_series_settings_default_to_current_behavior_and_are_plant_scoped(): void
    {
        $this->configureSeries(['batch_series_enabled' => 0, 'default_batch_series' => 'P']);
        $this->assertSame(['selection_enabled' => false, 'default_series' => 'P'], \App\Models\CustomSetting::batchSeriesSettings(1));
        $this->assertSame(['selection_enabled' => true, 'default_series' => 'NONE'], \App\Models\CustomSetting::batchSeriesSettings(2));
    }

    public function test_hidden_selector_uses_default_and_rejects_the_other_series(): void
    {
        $this->configureSeries(['batch_series_enabled' => 0, 'default_batch_series' => 'P']);
        foreach ([[[], 'P', true], [['batch_series' => 'B'], 'B', false], [['batch_series' => 'P'], 'P', true]] as [$input, $expected, $valid]) {
            $request = new class extends \App\Http\Requests\StoreBatchRequest {
                public function prepareSeries(): void { $this->prepareForValidation(); }
            };
            $request->merge($input);
            $request->prepareSeries();
            $this->assertSame($expected, $request->input('batch_series'));
            $validator = \Illuminate\Support\Facades\Validator::make($request->only('batch_series'), ['batch_series' => $request->rules()['batch_series']]);
            $this->assertSame($valid, $validator->passes());
        }
    }

    public function test_enabled_selector_allows_either_series(): void
    {
        $this->configureSeries(['batch_series_enabled' => 1, 'default_batch_series' => 'P']);
        foreach (['NONE', 'B', 'P'] as $series) {
            $request = \App\Http\Requests\StoreBatchRequest::create('/', 'POST', ['batch_series' => $series]);
            $validator = \Illuminate\Support\Facades\Validator::make(['batch_series' => $series], ['batch_series' => $request->rules()['batch_series']]);
            $this->assertTrue($validator->passes());
        }
    }

    public function test_custom_settings_save_preserves_batch_series_on_partial_updates(): void
    {
        $this->configureSeries([]);
        $controller = \Mockery::mock(\App\Http\Controllers\CustomSettingController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->twice()->with('edit');
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['module' => 'batching', 'settings' => ['batch_series_enabled' => false, 'default_batch_series' => 'P']]));
        $this->assertSame(0, \App\Models\CustomSetting::getForModule(1, 'batching')['batch_series_enabled']);
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['module' => 'batching', 'settings' => ['camera' => 1]]));
        $this->assertSame(['selection_enabled' => false, 'default_series' => 'P'], \App\Models\CustomSetting::batchSeriesSettings(1));
        $this->assertSame('B30', Batch::nextSeriesNumber(1, 'B')['batch_no']);
        $this->assertSame('P1', Batch::nextSeriesNumber(1, 'P')['batch_no']);
    }
}
