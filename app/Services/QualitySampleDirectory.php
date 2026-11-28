<?php
/*
Author: ragul-onemodo
Created: 2026-11-28 18:12:28 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\QC\QcSample;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class QualitySampleDirectory
{
    public const PAGE_SIZE = 30;

    public function page(int $plantId, array $filters, array $ages, string $timezone, bool $sourcePicker = false): array
    {
        // A dispatch-specific sample takes precedence over the latest sample for its batch.
        $latest = fn ($key) => QcSample::query()->where('plant_id', $plantId)->whereNotNull($key)
            ->groupBy($key)->select($key)->selectRaw('MAX(id) AS sample_id');
        $loads = DB::table('mm_dispatches as d')
            ->join('mm_sales_orders as o', function ($join) use ($plantId) {
                $join->on('o.id', '=', 'd.sales_order_id')->where('o.plant_id', $plantId)->whereNull('o.deleted_at');
            })
            ->leftJoinSub($latest('dispatch_id'), 'ds', 'ds.dispatch_id', '=', 'd.id')
            ->leftJoinSub($latest('batch_id'), 'bs', 'bs.batch_id', '=', 'd.batch_id')
            ->where('d.plant_id', $plantId)->whereNull('d.deleted_at')
            ->whereNotNull('d.dispatch_time')->whereNotNull('d.dispatch_no')
            ->whereNotIn('d.dispatch_status', ['Draft', 'Loading', 'Cancelled'])
            ->selectRaw('d.id as dispatch_id, o.id as order_id, COALESCE(ds.sample_id, bs.sample_id) as sample_id, d.id as linked_dispatch_id');
        $represented = DB::query()->fromSub(clone $loads, 'loads')->whereNotNull('sample_id')->select('sample_id');
        $standalone = QcSample::query()->where('plant_id', $plantId)->whereNotIn('id', $represented)
            ->selectRaw('NULL as dispatch_id, NULL as order_id, id as sample_id, dispatch_id as linked_dispatch_id');

        $rows = DB::query()->fromSub($loads->unionAll($standalone->toBase()), 'src')
            ->leftJoin('mm_qc_samples as s', 's.id', '=', 'src.sample_id')
            ->leftJoin('mm_qc_tests as t', fn ($j) => $j->on('t.sample_id', '=', 's.id')->whereNull('t.deleted_at')
                ->when($sourcePicker, fn ($j) => $j->whereRaw('1 = 0')))
            ->leftJoin('mm_dispatches as d', fn ($j) => $j->on('d.id', '=', 'src.linked_dispatch_id')
                ->where('d.plant_id', $plantId)->whereNull('d.deleted_at'))
            ->leftJoin('mm_batches as b', fn ($j) => $j->on('b.id', '=', DB::raw('CASE WHEN src.order_id IS NULL THEN s.batch_id ELSE d.batch_id END'))
                ->where('b.plant_id', $plantId)->whereNull('b.deleted_at'));

        $today = now($timezone)->startOfDay();
        $onDays = static function (Builder $query, array $days) use ($today) {
            foreach ($days as $day) {
                $start = $today->copy()->subDays($day);
                $query->orWhere(fn ($q) => $q->where('d.dispatch_time', '>=', $start)
                    ->where('d.dispatch_time', '<', $start->copy()->addDay()));
            }
        };
        if ($sourcePicker) {
            $rows->where(fn ($q) => $q->whereNull('b.status')->orWhere('b.status', '!=', 5))
                ->where(fn ($q) => $q->whereNull('d.dispatch_status')->orWhere('d.dispatch_status', '!=', 'Cancelled'));
        } else {
            $rows->where(fn ($q) => $q->whereNotNull('s.id')->orWhere(fn ($q) => $q
                ->where(fn ($q) => $q->whereNull('b.status')->orWhere('b.status', '!=', 5))
                ->where(fn ($q) => $onDays($q, $ages))));
        }
        if (isset($filters['age'])) {
            $rows->where(fn ($q) => $q->where('t.age_days', $filters['age'])->orWhere(fn ($q) => $q
                ->whereNull('t.age_days')->where(fn ($q) => $onDays($q, [$filters['age']]))));
        }
        if (isset($filters['status']) && $filters['status'] !== 'All') {
            $rows->whereRaw("COALESCE(NULLIF(t.overall_status, ''), CASE WHEN s.id IS NULL THEN 'not_sampled' ELSE 'pending' END) = ?", [$filters['status']]);
        }
        if (($search = trim($filters['search'] ?? '')) !== '') {
            $this->search($rows, $search, $plantId);
        }

        $total = $rows->count();
        $page = min((int) ($filters['page'] ?? 1), max(1, (int) ceil($total / self::PAGE_SIZE)));
        $entries = $rows->orderByDesc('src.order_id')->orderByDesc('src.dispatch_id')
            ->orderByDesc('src.sample_id')->orderBy('t.id')->forPage($page, self::PAGE_SIZE)
            ->get(['src.order_id', 'src.dispatch_id', 'src.sample_id', 't.id as test_id']);

        return compact('page', 'total', 'entries');
    }

    private function search(Builder $rows, string $search, int $plantId): void
    {
        $rows->leftJoin('mm_sales_orders as o', 'o.id', '=', 'src.order_id')
            ->leftJoin('mm_patrons as c', fn ($j) => $j->on('c.id', '=', DB::raw('CASE WHEN src.order_id IS NULL THEN s.customer_id ELSE o.customer_id END'))
                ->where('c.plant_id', $plantId)->where('c.operational_status', 'active')->whereNull('c.deleted_at'))
            ->leftJoin('mm_sites as site', fn ($j) => $j->on('site.id', '=', 'o.site_id')->whereNull('site.deleted_at')->where('site.plant_id', $plantId))
            ->leftJoin('mm_concrete_grades as grade', fn ($j) => $j->on('grade.id', '=', 's.concrete_grade_id')->whereNull('grade.deleted_at')->where('grade.plant_id', $plantId))
            ->leftJoin('mm_products as mat', fn ($j) => $j->on('mat.id', '=', 's.material_id')->whereNull('mat.deleted_at')->where('mat.plant_id', $plantId))
            ->leftJoin('mm_users as tester', fn ($j) => $j->on('tester.id', '=', 't.tested_by')->whereNull('tester.deleted_at'))
            ->leftJoin('mm_dispatch_statuses as ds', fn ($j) => $j->on('ds.dispatch_id', '=', 'src.dispatch_id')
                ->where('ds.id', '=', fn ($q) => $q->from('mm_dispatch_statuses as history')->selectRaw('MIN(history.id)')
                    ->whereColumn('history.dispatch_id', 'src.dispatch_id')->whereNull('history.deleted_at')))
            ->leftJoin('mm_invoices as inv', fn ($j) => $j->on('inv.id', '=', 'ds.invoice_id')->whereNull('inv.deleted_at')->where('inv.plant_id', $plantId));
        $pattern = '%'.strtr(mb_strtolower($search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
        $rows->where(function ($q) use ($pattern) {
            foreach (['o.order_no', 'c.legal_name', 'COALESCE(site.name, s.site_name)', 'b.batch_no', 'd.dispatch_no',
                "COALESCE(inv.invoice_number, ds.invoice_number, 'Not invoiced')", 's.sample_no', 's.truck_no',
                's.source_location', 'grade.name', 'mat.title', 't.test_no', 'tester.username'] as $field) {
                $q->orWhereRaw("LOWER({$field}) LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }
}
