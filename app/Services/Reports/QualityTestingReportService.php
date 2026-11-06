<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 10:02:11 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Reports;

use App\Models\QC\QcTest;
use App\Services\PlantContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class QualityTestingReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        Validator::make($params, ['start' => 'required|date', 'end' => 'required|date|after_or_equal:start',
            'qc_category' => 'nullable|in:Concrete,Raw Material'])->validate();
        $start = Carbon::parse($params['start'])->startOfDay();
        $end = Carbon::parse($params['end'])->addDay()->startOfDay();
        $groups = QcTest::where('plant_id', $this->ctx->requirePlantId())
            ->when(!empty($params['qc_category']), fn ($query) => $query->whereHas('testType', fn ($type) => $type
                ->where('plant_id', $this->ctx->requirePlantId())
                ->whereRaw('LOWER(TRIM(category)) = ?', [strtolower($params['qc_category'])])))
            ->whereRaw('COALESCE(test_date, scheduled_date) >= ?', [$start->toDateString()])
            ->whereRaw('COALESCE(test_date, scheduled_date) < ?', [$end->toDateString()])
            ->selectRaw('age_days, LOWER(overall_status) as result_status, COUNT(*) as test_count')
            ->groupBy('age_days')->groupByRaw('LOWER(overall_status)')->get();
        $columns = ['age' => 'Test Age', 'total' => 'Total Count', 'completed' => 'Completed Count',
            'pending' => 'Pending Count', 'passed' => 'Passed Count', 'failed' => 'Failed Count',
            'retest' => 'Retest Count', 'hold' => 'Hold / Other Count'];
        $rows = collect([7, 15, 28])->merge($groups->pluck('age_days'))->unique()->sort()->values()->map(function ($age) use ($groups) {
            $items = $groups->filter(fn ($group) => $group->age_days === $age);
            $counts = $items->pluck('test_count', 'result_status');
            $count = fn ($status) => (int) ($counts[$status] ?? 0);
            $completed = $count('pass') + $count('fail') + $count('completed');
            $total = (int) $items->sum('test_count');
            return ['age' => $age === null ? 'Unspecified' : $age.' days', 'total' => $total,
                'completed' => $completed, 'pending' => $count('pending'), 'passed' => $count('pass'),
                'failed' => $count('fail'), 'retest' => $count('retest'),
                'hold' => $total - $completed - $count('pending') - $count('retest')];
        });
        $totals = collect(array_keys($columns))->reject(fn ($key) => $key === 'age')
            ->mapWithKeys(fn ($key) => [$key => $rows->sum($key)])->all();
        return ['transactions' => $rows->all(), 'totals' => $totals,
            'headers' => array_values($columns), 'fields' => array_keys($columns),
            'alignments' => array_map(fn ($key) => $key === 'age' ? 'left' : 'right', array_keys($columns))];
    }

    public function targetName(array $params): string { return 'Quality Testing Report'; }
}
