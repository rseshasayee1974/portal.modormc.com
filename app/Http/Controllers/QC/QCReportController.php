<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\QC\QcSample;
use App\Models\QC\QcTest;
use App\Models\QC\QcTestResult;
use App\Models\Product;
use App\Models\Patron;
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
}
