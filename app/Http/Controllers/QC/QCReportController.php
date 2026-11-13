<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
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
    use AuthorizesModule;
    protected string $module = 'QC_REPORT';

    public function index(Request $request)
    {
        $this->authorizeModule('menu');

        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $query = $this->reportQuery($request, $plantId);

        $counts = (clone $query)->toBase()->selectRaw("
            COUNT(*) AS total,
            COUNT(CASE WHEN overall_status = 'pass' THEN 1 END) AS passed,
            COUNT(CASE WHEN overall_status = 'fail' THEN 1 END) AS failed,
            COUNT(CASE WHEN overall_status = 'retest' THEN 1 END) AS retest
        ")->first();
        $allCount = (int) $counts->total;
        $passCount = (int) $counts->passed;
        $failCount = (int) $counts->failed;
        $retestCount = (int) $counts->retest;
        $passRate = $allCount > 0 ? round(($passCount / $allCount) * 100, 2) : 0;

        $tests = $query->withOnly([
            'sample:id,sample_no,material_id,supplier_id',
            'sample.material:id,title',
            'sample.supplier:id,legal_name',
            'testType:id,name',
        ])->orderByDesc('test_date')->orderByDesc('id')
            ->paginate(20, ['id', 'test_date', 'test_no', 'sample_id', 'test_type_id', 'overall_status'], total: $allCount)
            ->withQueryString();

        // Only serialize the fields displayed in the table, skipping model accessors
        // such as the supplier's usage checks.
        $tests->through(fn (QcTest $test) => [
            ...$test->only(['id', 'test_date', 'test_no', 'overall_status']),
            'sample' => $test->sample ? [
                'sample_no' => $test->sample->sample_no,
                'material' => $test->sample->material?->only(['title']),
                'supplier' => $test->sample->supplier?->only(['legal_name']),
            ] : null,
            'test_type' => $test->testType?->only(['name']),
        ]);

        return Inertia::render('Quality/Reports/Index', [
            'tests' => $tests,
            'summary' => [
                'total' => $allCount,
                'passed' => $passCount,
                'failed' => $failCount,
                'retest' => $retestCount,
                'pass_rate' => $passRate,
            ],
            'materials' => fn () => Product::query()
                ->when($plantId, fn ($products) => $products->where('plant_id', $plantId))
                ->where('status', true)->toBase()->get(['id', 'title']),
            'filters' => $request->only(['start_date', 'end_date', 'material_id', 'status']),
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $this->authorizeModule('pdf');

        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $query = $this->reportQuery($request, $plantId);

        $tests = $query->orderBy('test_date', 'desc')->get();

        $pdf = Pdf::loadView('reports.qc_summary_pdf', [
            'tests' => $tests,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return $pdf->download('QC_Summary_Report_' . date('Ymd_His') . '.pdf');
    }

    private function reportQuery(Request $request, ?int $plantId)
    {
        // Accept existing bookmarked report URLs as well as the date-range fields.
        $request->merge([
            'start_date' => $request->input('start_date', $request->input('date_from')),
            'end_date' => $request->input('end_date', $request->input('date_to')),
        ]);
        $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', ...($request->filled('start_date') ? ['after_or_equal:start_date'] : [])],
        ]);
        $query = QcTest::with(['sample.material', 'sample.supplier', 'sample.customer', 'sample.concreteGrade', 'testType', 'results.parameter']);

        if ($plantId) {
            $query->where('plant_id', $plantId);
        }

        if ($request->input('start_date')) {
            $query->whereDate('test_date', '>=', $request->input('start_date'));
        }

        if ($request->input('end_date')) {
            $query->whereDate('test_date', '<=', $request->input('end_date'));
        }

        if ($request->material_id) {
            $query->whereHas('sample', fn($s) => $s->where('material_id', $request->material_id));
        }

        if ($request->status && $request->status !== 'All') {
            $query->where('overall_status', strtolower($request->status));
        }

        return $query;
    }

    public function downloadCubeReportPdf(Request $request, QcTest $test)
    {
        $this->authorizeModule('pdf', 'QC_TEST');
        $plantId = app(PlantContextService::class)->requirePlantId();
        abort_unless((int) $test->plant_id === $plantId && $test->sample()->where('plant_id', $plantId)->exists(), 404);
        abort_unless(app(\App\Services\QC\ConcreteCubeCalculator::class)->supports($test), 422, 'Concrete strength certificates are available for cube compression tests.');
        $request->validate(['format' => 'nullable|in:certificate,worksheet']);

        $test->loadMissing([
            'plant.entity',
            'plant.addresses',
            'sample.material',
            'sample.supplier',
            'sample.customer' => fn ($query) => $query->withoutGlobalScope('active_operational_status')->where('plant_id', $test->plant_id),
            'sample.concreteGrade',
            'sample.dispatch.truck',
            'sample.dispatch.salesOrder.customer',
            'sample.dispatch.salesOrder.site',
            'sample.dispatch.unloadSite',
            'config.standard',
            'sets.specimens',
            'testType.parameters',
            'tester',
            'reviewer'
        ]);

        $plant = $test->plant;

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

        $worksheet = $request->input('format') === 'worksheet';
        $reportData = app(\App\Services\QC\CubeReportDataService::class);
        $reportSets = $worksheet ? $reportData->sampleSets($test) : $reportData->certificateSets($test);
        abort_if($reportSets->isEmpty(), 422, 'Complete all specimen weights and loads before generating a concrete strength certificate.');
        $pdf = Pdf::loadView($worksheet ? 'reports.cube_test_report_pdf' : 'reports.concrete_strength_certificate_pdf', [
            'test' => $test,
            'reportSets' => $reportSets,
            'reportTestNumbers' => $reportSets->pluck('source_test_no')->unique()->implode(', '),
            'plant' => $plant,
            'companyName' => $plant?->entity?->legal_name ?? $plant?->name ?? $test->plant?->entity?->legal_name ?? $test->plant?->name ?? config('app.name'),
            'companyAddress' => collect([$address?->line_1, $address?->line_2, $address?->city, $address?->zipcode])->filter()->implode(', '),
            'companyLogo' => $companyLogo,
            'plantLogo' => $companyLogo,
            'reportDate' => now()->format('d/m/Y'),
            'clientName' => $test->sample?->customer?->legal_name ?? $test->sample?->dispatch?->salesOrder?->customer?->legal_name ?? 'N/A',
            'projectName' => $test->sample?->dispatch?->unloadSite?->name ?? $test->sample?->dispatch?->salesOrder?->site?->name ?? $test->sample?->site_name ?? 'N/A',
            'siteAddress' => $test->sample?->site_name ?? $test->sample?->dispatch?->site_address ?? $test->sample?->source_location ?? 'N/A',
            'gradeName' => $test->sample?->concreteGrade?->name ?? '',
            'cubeSize' => $test->config?->specimen_dimensions ?? $test->sample?->specimen_size ?? $test->testType?->specimen_dimensions ?? '',
        ])->setPaper('a4', $worksheet ? 'landscape' : 'portrait');

        $cleanTestNo = preg_replace('/[^A-Za-z0-9_-]/', '_', $worksheet ? ($test->sample?->sample_no ?? $test->test_no) : $test->test_no);
        $filename = "Cube_Test_Report_{$cleanTestNo}.pdf";
        return $request->boolean('inline') ? $pdf->stream($filename) : $pdf->download($filename);
    }
}
