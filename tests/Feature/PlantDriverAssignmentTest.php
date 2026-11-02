<?php

/*
Author: ragul-onemodo
Created: 2026-11-02 11:01:51 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Http\Controllers\CustomSettingController;
use App\Jobs\ProcessBatchSheetJob;
use App\Models\BatchSheetUpload;
use App\Services\BatchSheet\DataNormalizer;
use App\Services\BatchSheet\Drivers\JayadhityaGroupsPlant739Driver;
use App\Services\BatchSheet\Drivers\PlantDriverRegistry;
use App\Services\BatchSheet\DTOs\ParsedDocument;
use App\Services\BatchSheet\FieldExtractor;
use App\Services\BatchSheet\ParserRegistry;
use App\Services\BatchSheet\Parsers\PdfTextParser;
use App\Services\BatchSheet\TemplateMatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use UnexpectedValueException;

class PlantDriverAssignmentTest extends TestCase
{
    private PlantDriverRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::statement('CREATE TABLE mm_custom_settings (id INTEGER PRIMARY KEY, plant_id INTEGER, module_name TEXT, module_id INTEGER, settings TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
        $this->registry = new PlantDriverRegistry;
        $this->app->instance(PlantDriverRegistry::class, $this->registry);
    }

    private function assign(int $plantId, ?string $code): void
    {
        DB::table('mm_custom_settings')->where('plant_id', $plantId)->delete();
        DB::table('mm_custom_settings')->insert(['plant_id' => $plantId, 'module_name' => 'batching', 'settings' => json_encode(['batch_sheet_driver' => $code, 'po_prefix' => 'KEEP'])]);
    }

    public function test_assignment_overrides_serial_and_signatures_only_for_its_plant(): void
    {
        $this->assign(1, 'jayadhitya_groups_plant_739');
        $driver = $this->registry->resolve('Other plant format', ['plant_id' => 1, 'plant_serial' => '121']);
        $this->assertInstanceOf(JayadhityaGroupsPlant739Driver::class, $driver);
        $other = $this->registry->resolve('', ['plant_id' => 2, 'plant_serial' => '121']);
        $this->assertSame('121', $other->getPlantSerial());
    }

    public function test_queue_context_uses_explicit_plant_instead_of_session(): void
    {
        $this->assign(1, 'jayadhitya_groups_plant_739');
        session(['active_plant_id' => 2]);
        $this->assertInstanceOf(JayadhityaGroupsPlant739Driver::class, $this->registry->resolve('', ['plant_id' => 1]));
    }

    public function test_unassigned_or_cleared_driver_keeps_automatic_detection(): void
    {
        $this->assign(1, null);
        $this->assertSame('121', $this->registry->resolve('', ['plant_id' => 1, 'plant_serial' => '121'])->getPlantSerial());
        $this->assertNull($this->registry->assignedDriver(2));
    }

    public function test_unavailable_driver_is_reported_instead_of_selecting_another_format(): void
    {
        $this->assign(1, 'removed_driver');
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('assigned to plant 1 is unavailable');
        $this->registry->resolve('', ['plant_id' => 1, 'plant_serial' => '121']);
    }

    private function controller(): CustomSettingController
    {
        return new class extends CustomSettingController {
            protected function authorizeModule(string $action, ?string $module = null): void {}
        };
    }

    public function test_settings_save_only_affects_selected_plant_and_can_restore_auto_detection(): void
    {
        $this->assign(1, null);
        $this->assign(2, 'jayadhitya_groups_plant_739');
        session(['active_plant_id' => 1]);
        $save = fn ($settings) => Model::withoutEvents(fn () => $this->controller()->update(Request::create('/', 'POST', ['module' => 'batching', 'settings' => $settings])));
        $save(['batch_sheet_driver' => 'jayadhitya_groups_plant_739', 'po_prefix' => 'KEEP']);
        $this->assertInstanceOf(JayadhityaGroupsPlant739Driver::class, $this->registry->assignedDriver(1));
        $save(['po_prefix' => 'EDITED']);
        $this->assertInstanceOf(JayadhityaGroupsPlant739Driver::class, $this->registry->assignedDriver(1));
        $save(['batch_sheet_driver' => null, 'po_prefix' => 'EDITED']);
        $this->assertNull($this->registry->assignedDriver(1));
        $this->assertInstanceOf(JayadhityaGroupsPlant739Driver::class, $this->registry->assignedDriver(2));
    }

    public function test_settings_reject_unknown_driver_codes(): void
    {
        session(['active_plant_id' => 1]);
        try {
            $this->controller()->update(Request::create('/', 'POST', ['module' => 'batching', 'settings' => ['batch_sheet_driver' => 'SomeArbitraryClass']]));
            $this->fail('An unknown driver was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('settings.batch_sheet_driver', $exception->errors());
        }
    }

    public function test_upload_job_passes_plant_id_without_a_browser_session_and_records_driver(): void
    {
        $this->runUploadJob(false);
    }

    public function test_assigned_driver_failure_does_not_fall_back_to_another_parser(): void
    {
        $this->runUploadJob(true);
    }

    private function runUploadJob(bool $fail): void
    {
        $this->assign(1, 'jayadhitya_groups_plant_739');
        DB::statement('CREATE TABLE mm_batch_sheet_uploads (id INTEGER PRIMARY KEY, plant_id INTEGER, stored_path TEXT, mime_type TEXT, file_extension TEXT, original_filename TEXT, status TEXT, parser_used TEXT, ocr_required INTEGER, error_message TEXT, processing_log TEXT, processing_started_at TEXT, processing_completed_at TEXT, raw_text TEXT, parsed_json TEXT, normalized_json TEXT, confidence_score DECIMAL, field_scores TEXT, template_id INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
        $path = tempnam(storage_path('app'), 'driver-test-');
        DB::table('mm_batch_sheet_uploads')->insert(['id' => 1, 'plant_id' => 1, 'stored_path' => basename($path), 'mime_type' => 'application/pdf', 'file_extension' => 'pdf', 'original_filename' => 'sample.pdf']);
        session()->forget('active_plant_id');
        $parsePath = storage_path('app/'.basename($path));
        $pdf = Mockery::mock(PdfTextParser::class)->makePartial();
        $parse = $pdf->shouldReceive('parse')->once()->with($parsePath, ['mime_type' => 'application/pdf', 'plant_id' => 1]);
        if ($fail) $parse->andThrow(new \RuntimeException('Assigned format failed'));
        else $parse->andReturn(new ParsedDocument(['headerFields' => ['batch_number' => '1'], 'materialRows' => [['material_name' => 'Cement']], 'parserUsed' => 'Assigned Driver']));
        $parsers = Mockery::mock(ParserRegistry::class);
        $parsers->shouldReceive('detectOcrRequired')->once()->with($parsePath)->andReturn(false);
        $parsers->shouldReceive('resolve')->once()->andReturn($pdf);
        $parsers->shouldNotReceive('getFallbackParser');
        $matcher = Mockery::mock(TemplateMatcher::class);
        $extractor = Mockery::mock(FieldExtractor::class);
        $normalizer = Mockery::mock(DataNormalizer::class);
        if (!$fail) {
            $matcher->shouldReceive('match')->once()->andReturn(null);
            $extractor->shouldReceive('extract')->once()->andReturn(['header' => [], 'field_scores' => []]);
            $normalizer->shouldReceive('normalize')->once()->andReturn(['header' => [], 'materials' => []]);
        }
        try {
            Model::withoutEvents(fn () => (new ProcessBatchSheetJob(1))->handle($parsers, $matcher, $extractor, $normalizer));
            $upload = BatchSheetUpload::findOrFail(1);
            $this->assertSame($fail ? BatchSheetUpload::STATUS_FAILED : BatchSheetUpload::STATUS_REVIEW, $upload->status, (string) $upload->error_message);
            if ($fail) $this->assertSame('Assigned format failed', $upload->error_message);
            else $this->assertSame('Assigned Driver', $upload->parser_used);
        } finally {
            unlink($path);
        }
    }
}
