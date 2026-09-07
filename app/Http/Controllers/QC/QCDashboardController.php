<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\QC\QcSample;
use App\Models\QC\QcTest;
use App\Models\QC\QcTestResult;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class QCDashboardController extends Controller
{
    public function index(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $samplesQuery = QcSample::query();
        $testsQuery = QcTest::query();

        if ($plantId) {
            $samplesQuery->where('plant_id', $plantId);
            $testsQuery->where('plant_id', $plantId);
        }

        $totalTests = (clone $testsQuery)->count();
        $passCount = (clone $testsQuery)->where('overall_status', 'pass')->count();
        $failCount = (clone $testsQuery)->where('overall_status', 'fail')->count();
        $retestCount = (clone $testsQuery)->where('overall_status', 'retest')->count();
        $pendingCount = (clone $testsQuery)->where('overall_status', 'pending')->count();

        $passRate = $totalTests > 0 ? round(($passCount / $totalTests) * 100, 1) : 0.0;

        $recentTests = (clone $testsQuery)->with(['sample.material', 'sample.supplier', 'testType', 'tester'])
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $recentFailures = (clone $testsQuery)->with(['sample.material', 'sample.supplier', 'testType', 'results.parameter'])
            ->whereIn('overall_status', ['fail', 'retest'])
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        return Inertia::render('Quality/Dashboard', [
            'stats' => [
                'total_tests' => $totalTests,
                'passed' => $passCount,
                'failed' => $failCount,
                'retest' => $retestCount,
                'pending' => $pendingCount,
                'pass_rate' => $passRate,
            ],
            'recentTests' => $recentTests,
            'recentFailures' => $recentFailures,
        ]);
    }
}
