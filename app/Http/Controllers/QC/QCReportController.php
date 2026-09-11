<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\QC\QcSample;
use App\Models\QC\QcTest;
use App\Models\QC\QcTestResult;
use App\Models\Product;
use App\Models\Patron;
use App\Models\Plant;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class QCReportController extends Controller
{
    public function index(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $query = QcTest::with(['sample.material', 'sample.supplier', 'sample.customer', 'sample.concreteGrade', 'testType', 'results.parameter']);

        if ($plantId) {
            $query->where('plant_id', $plantId);
        }

        if ($request->date_from) {
            $query->whereDate('test_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('test_date', '<=', $request->date_to);
        }

        if ($request->material_id) {
            $query->whereHas('sample', fn($s) => $s->where('material_id', $request->material_id));
        }

        if ($request->status && $request->status !== 'All') {
            $query->where('overall_status', strtolower($request->status));
        }

        $tests = $query->orderBy('test_date', 'desc')->paginate(20)->withQueryString();

        // Metrics Summary
        $allCount = (clone $query)->count();
        $passCount = (clone $query)->where('overall_status', 'pass')->count();
        $failCount = (clone $query)->where('overall_status', 'fail')->count();
        $retestCount = (clone $query)->where('overall_status', 'retest')->count();
        $passRate = $allCount > 0 ? round(($passCount / $allCount) * 100, 2) : 0;

        $materials = Product::query();
        if ($plantId) {
            $materials->where('plant_id', $plantId);
        }
        $materials = $materials->where('status', true)->get(['id', 'title', 'code']);

        return Inertia::render('Quality/Reports/Index', [
            'tests' => $tests,
            'summary' => [
                'total' => $allCount,
                'passed' => $passCount,
                'failed' => $failCount,
                'retest' => $retestCount,
                'pass_rate' => $passRate,
            ],
            'materials' => $materials,
            'filters' => $request->only(['date_from', 'date_to', 'material_id', 'status']),
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $query = QcTest::with(['sample.material', 'sample.supplier', 'sample.customer', 'sample.concreteGrade', 'testType', 'results.parameter']);

        if ($plantId) {
            $query->where('plant_id', $plantId);
        }

        $tests = $query->orderBy('test_date', 'desc')->get();

        $pdf = Pdf::loadView('reports.qc_summary_pdf', [
            'tests' => $tests,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return $pdf->download('QC_Summary_Report_' . date('Ymd_His') . '.pdf');
    }

    public function downloadCubeReportPdf(Request $request, QcTest $test)
    {
        $test->loadMissing([
            'plant.entity',
            'plant.addresses',
            'sample.material',
            'sample.supplier',
            'sample.customer',
            'sample.concreteGrade',
            'sample.dispatch.truck',
            'sample.dispatch.salesOrder.customer',
            'config.standard',
            'sets.specimens',
            'testType.parameters',
            'tester',
            'reviewer'
        ]);

        $activePlantId = app(PlantContextService::class)->plantId() ?? session('active_plant_id');
        $plant = ($activePlantId ? Plant::find($activePlantId) : null) ?? $test->plant ?? Plant::first();

        $address = $plant?->addresses?->firstWhere('is_primary', true)
            ?? $plant?->addresses?->first()
            ?? $test->plant?->addresses?->firstWhere('is_primary', true)
            ?? $test->plant?->addresses?->first();

        $companyLogo = null;
        if ($plant && !empty($plant->logo_path)) {
            $cleanLogo = ltrim(str_replace(['public/', 'storage/', '/storage/'], '', $plant->logo_path), '/\\');
            $possiblePaths = [
                storage_path('app/public/' . $cleanLogo),
                public_path('storage/' . $cleanLogo),
                base_path('storage/app/public/' . $cleanLogo),
                base_path('public/storage/' . $cleanLogo),
            ];
            foreach ($possiblePaths as $pPath) {
                if (file_exists($pPath)) {
                    $mime = mime_content_type($pPath) ?: 'image/png';
                    $companyLogo = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($pPath));
                    break;
                }
            }
        }

        $reportSets = app(\App\Services\QC\CubeReportDataService::class)->sampleSets($test);
        $pdf = Pdf::loadView('reports.cube_test_report_pdf', [
            'test' => $test,
            'reportSets' => $reportSets,
            'reportTestNumbers' => $reportSets->pluck('source_test_no')->unique()->implode(', '),
            'plant' => $plant,
            'companyName' => $plant?->entity?->legal_name ?? $plant?->name ?? $test->plant?->entity?->legal_name ?? $test->plant?->name ?? config('app.name'),
            'companyAddress' => collect([$address?->line_1, $address?->line_2, $address?->city, $address?->zipcode])->filter()->implode(', '),
            'companyLogo' => $companyLogo,
            'plantLogo' => $companyLogo,
            'reportDate' => now()->format('d/m/Y'),
            'clientName' => $test->sample?->customer?->name ?? 'N/A',
            'siteAddress' => $test->sample?->site_name ?? $test->sample?->dispatch?->site_address ?? $test->sample?->source_location ?? 'N/A',
            'gradeName' => $test->sample?->concreteGrade?->name ?? '',
            'cubeSize' => $test->config?->specimen_dimensions ?? $test->sample?->specimen_size ?? $test->testType?->specimen_dimensions ?? '',
        ])->setPaper('a4', 'landscape');

        $cleanTestNo = preg_replace('/[^A-Za-z0-9_-]/', '_', $test->sample?->sample_no ?? $test->test_no);
        $filename = "Cube_Test_Report_{$cleanTestNo}.pdf";
        return $request->boolean('inline') ? $pdf->stream($filename) : $pdf->download($filename);
    }
}
