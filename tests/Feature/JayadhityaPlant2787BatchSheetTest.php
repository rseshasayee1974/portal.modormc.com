<?php
/*
Author: ragul-onemodo
Created: 2026-11-02 16:49:44 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Services\{JayadhityaPlant2787BatchSheet, JayadhityaBatchSheet};
use App\Services\BatchSheet\Drivers\{JayadhityaPlant2787Driver, PlantDriverRegistry};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\Fixtures\JayadhityaPlant2787Fixture;

class JayadhityaPlant2787BatchSheetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'plant2787_test', 'database.connections.plant2787_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Schema::create('mm_custom_settings', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->string('module_name'); $table->json('settings'); $table->softDeletes();
        });
        DB::table('mm_custom_settings')->insert(['plant_id' => 2, 'module_name' => 'batching', 'settings' => json_encode(['batch_sheet_driver' => JayadhityaPlant2787Driver::CODE])]);
    }

    public function test_assigned_profile_selects_plant_two_layout_and_upload_driver(): void
    {
        $batch = JayadhityaPlant2787Fixture::batch();
        $batch->plant->code = 'PLT-8791';
        $this->assertTrue(JayadhityaPlant2787BatchSheet::matches($batch));
        $this->assertInstanceOf(JayadhityaPlant2787Driver::class, (new PlantDriverRegistry)->assignedDriver(2));
        $html = view('pdfs.batches.batch_sheet', ['batch' => $batch])->render();
        $this->assertStringContainsString('MCI 370 Control System Ver 1.0', $html);
        $this->assertStringContainsString('Docket / Batch Report / Autographic Record', $html);
        $this->assertStringContainsString('TN18AS5947', $html);
        $this->assertStringContainsString('07:54:48 AM', $html);
        $this->assertSame(6, substr_count($html, 'data-row="actual"'));
        $doc = new \DOMDocument;
        @$doc->loadHTML($html);
        $xpath = new \DOMXPath($doc);
        $this->assertSame(['Aggregate', 'Cement', 'Water', 'MS / ICE', 'Admixture'],
            array_map(fn ($cell) => $cell->textContent, iterator_to_array($xpath->query('//tr[@class="group"]/th'))));
        $this->assertSame('Moi', $xpath->query('//tr[@class="heads"]/th[3]')->item(0)->textContent);
        $this->assertSame('+/-', $xpath->query('//tr[@data-row="target"]/td[11]')->item(0)->textContent);
        $this->assertSame('806', $xpath->query('//tr[@data-row="actual"][1]/td[2]')->item(0)->textContent);
        $this->assertSame('907', $xpath->query('//tr[@data-row="actual"][6]/td[2]')->item(0)->textContent);
        $this->assertSame('5061', $xpath->query('//tr[@data-row="total-actual"]/td[2]')->item(0)->textContent);
        $this->assertSame('5100', $xpath->query('//tr[@data-row="total-set"]/td[2]')->item(0)->textContent);
    }

    public function test_unknown_cycles_remain_load_totals_and_extra_materials_are_not_lost(): void
    {
        $batch = JayadhityaPlant2787Fixture::batch();
        $batch->setRelation('materials', collect([(new \App\Models\BatchMaterial(['material_name' => 'SPECIAL FILLER', 'target_qty' => 15, 'actual_qty' => 16]))->setRelation('product', null)]));
        $report = JayadhityaPlant2787BatchSheet::data($batch);
        $this->assertCount(0, $report['actual_cells']);
        $this->assertTrue($report['inferred_runs']);
        $this->assertSame(['SPECIAL FILLER', 'SPECIAL FILLER'], $report['slots'][0]);
        $this->assertSame('16', $report['total_cells'][0]);
        $html = view('pdfs.batches.batch_sheet', ['batch' => $batch])->render();
        $this->assertStringContainsString('Individual mixer-cycle readings are unavailable.', $html);
    }

    public function test_other_plant_keeps_existing_layout(): void
    {
        $batch = JayadhityaPlant2787Fixture::batch();
        $batch->plant_id = 739;
        $batch->plant->id = 739;
        $batch->plant->code = '739';
        $this->assertFalse(JayadhityaPlant2787BatchSheet::matches($batch));
        $html = view('pdfs.batches.batch_sheet', ['batch' => $batch])->render();
        $this->assertStringContainsString('BATCH SHEET REPORT', $html);
        $this->assertStringNotContainsString('MCI 370 Control System Ver 1.0', $html);
    }

    public function test_normalization_preserves_original_date_and_cycle_readings(): void
    {
        Schema::create('mm_batch_sheet_templates', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->boolean('is_active');
            $table->json('material_mapping')->nullable(); $table->softDeletes();
        });
        $normalizer = \Mockery::mock(\App\Services\BatchSheet\DataNormalizer::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $normalizer->shouldReceive('resolveProduct')->with('MSAND 1', 2)->andReturn(1);
        $data = $normalizer->normalize(['batch_number' => '2470', 'batch_date' => '2026-11-01', 'batch_size' => 6,
            'batch_start_time' => '07:54:48 AM'], [['material_name' => 'MSAND 1', 'target_qty' => 5100,
            'actual_qty' => 5061, 'runs' => [806,816,831,849,852,907]]], 2);
        $this->assertSame('2026-11-01', $data['header']['batch_date']);
        $this->assertSame('07:54:48', $data['header']['start_time']);
        $this->assertSame([806.0,816.0,831.0,849.0,852.0,907.0], $data['materials'][0]['runs']);
    }

    public function test_upload_rejects_cycle_totals_that_disagree_with_verified_actual(): void
    {
        Schema::create('mm_batch_sheet_uploads', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->softDeletes();
        });
        DB::table('mm_batch_sheet_uploads')->insert(['id' => 1, 'plant_id' => 2]);
        $request = \Illuminate\Http\Request::create('/', 'POST', ['header' => ['batch_no' => '2470'],
            'materials' => [['actual_qty' => 999, 'runs' => [806,816,831,849,852,907]]]]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Http\Controllers\Api\BatchSheetUploadController::class)->saveToDatabase($request, 1);
    }

    public function test_sales_order_matching_uses_customer_for_shared_references(): void
    {
        Schema::create('mm_sales_orders', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->integer('customer_id');
            $table->string('prefix'); $table->string('order_no'); $table->softDeletes();
        });
        DB::table('mm_sales_orders')->insert([
            ['id' => 1, 'plant_id' => 2, 'customer_id' => 10, 'prefix' => 'SO/26-27/', 'order_no' => '1'],
            ['id' => 2, 'plant_id' => 2, 'customer_id' => 11, 'prefix' => 'SO/26-27/', 'order_no' => '1'],
        ]);
        $normalizer = new class extends \App\Services\BatchSheet\DataNormalizer {
            public function resolveSalesOrder(string $orderNo, int $plantId, ?int $customerId = null): ?int
            {
                return parent::resolveSalesOrder($orderNo, $plantId, $customerId);
            }
        };
        $this->assertSame(2, $normalizer->resolveSalesOrder('SO/26-27/1', 2, 11));
        $this->assertNull($normalizer->resolveSalesOrder('SO/26-27/1', 2));
    }
}
