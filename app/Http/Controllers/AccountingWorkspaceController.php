<?php

/*
Author: ragul-onemodo
Created: 2026-10-18 21:18:12 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers;

use App\Services\Finance\AccountingReports;
use App\Services\Finance\AccountingTally;
use App\Services\Finance\AccountingWorkspace;
use App\Services\Gst\GstAccess;
use App\Services\Gst\GstData;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

final class AccountingWorkspaceController extends Controller
{
    public function __construct(private readonly PlantContextService $context, private readonly GstAccess $access, private readonly AccountingWorkspace $workspace, private readonly AccountingReports $reports) {}

    private const TABLES = ['mm_accounting_accounts', 'mm_accounting_periods', 'mm_accounting_journals', 'mm_accounting_events', 'mm_accounting_tally', 'mm_accounting_settings'];

    private function installed(): bool
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function scope(Request $r, string $action = 'VIEW', bool $requireInstalled = true): array
    {
        abort_unless($r->user(), 401);
        $plant = $this->context->requirePlantId();
        $permissions = $this->access->forModule($r->user(), $plant, 'ACCOUNTING', AccountingWorkspace::ACTIONS);
        abort_unless($permissions[$action] ?? false, 403, 'Accounting permission required: '.$action);
        $v = $r->validate(['plant_id' => 'sometimes|required|integer|min:1']);
        abort_if(isset($v['plant_id']) && (int) $v['plant_id'] !== $plant, 409, 'Active plant changed. Reload this page before continuing.');
        abort_if($requireInstalled && ! $this->installed(), 503, 'Accounting setup is incomplete. Ask the administrator to run php artisan accounting:preflight, resolve the reported blockers, and apply the accounting workspace migration.');

        return [$plant, (int) $r->user()->id, $permissions];
    }

    private function range(Request $r): array
    {
        $v = validator(['from' => $r->input('from', now()->startOfMonth()->toDateString()), 'to' => $r->input('to', now()->toDateString()), 'page' => $r->input('page', 1), 'search' => $r->input('search', '')], [
            'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'page' => 'required|integer|min:1|max:100000', 'search' => 'nullable|string|max:100',
        ])->validate();

        return [$v['from'], $v['to'], (int) $v['page'], $v['search'] ?? ''];
    }

    private function payload(Request $r, int $plant, array $permissions): array
    {
        [$from, $to, $page, $search] = $this->range($r);

        return ['plant_id' => $plant, 'permissions' => $permissions, 'summary' => $this->reports->summary($plant, $from, $to),
            'accounts' => $this->reports->accounts($plant), 'journals' => $this->reports->journals($plant, $from, $to, $search, $page)['rows'],
            'groups' => DB::table('mm_account_types')->where('plant_id', $plant)->whereNull('deleted_at')->orderBy('title')->get(['id', 'title']),
            'periods' => DB::table('mm_accounting_periods')->where('plant_id', $plant)->orderByDesc('period')->get(),
            'events' => DB::table('mm_accounting_events')->where('plant_id', $plant)->orderByDesc('id')->paginate(50, ['id', 'actor_id', 'action', 'payload', 'digest', 'created_at'], 'page', $page),
            'tally' => $permissions['TALLY'] ? ['settings' => DB::table('mm_accounting_settings')->where('plant_id', $plant)->first(),
                'configured_listener' => (bool) config('gst_compliance.tally_url'), 'jobs' => DB::table('mm_accounting_tally')->where('plant_id', $plant)->orderByDesc('id')->paginate(50, ['id', 'journal_id', 'company', 'guid', 'digest', 'status', 'attempts', 'last_error', 'updated_at'], 'page', $page)] : null];
    }

    public function index(Request $r): mixed
    {
        [$plant, , $permissions] = $this->scope($r, 'VIEW', false);

        if (! $this->installed()) {
            return Inertia::render('Accounting/Setup', ['plant_id' => $plant]);
        }

        return Inertia::render('Accounting/Index', ['workspace' => $this->payload($r, $plant, $permissions)]);
    }

    public function data(Request $r): mixed
    {
        [$plant, , $permissions] = $this->scope($r);

        return response()->json($this->payload($r, $plant, $permissions));
    }

    public function account(Request $r, ?int $id = null): mixed
    {
        [$p, $actor] = $this->scope($r, $id ? 'UPDATE' : 'CREATE');

        return response()->json($this->workspace->account($p, $actor, $r->all(), $id), $id ? 200 : 201);
    }

    public function journal(Request $r, ?int $id = null): mixed
    {
        [$p, $actor] = $this->scope($r, $id ? 'UPDATE' : 'CREATE');

        return response()->json($this->workspace->journal($p, $actor, $r->all(), $id), $id ? 200 : 201);
    }

    public function entry(Request $r, int $id): mixed
    {
        [$p] = $this->scope($r);

        return response()->json($this->workspace->entry($p, $id));
    }

    public function post(Request $r, int $id): mixed
    {
        [$p, $actor] = $this->scope($r, 'POST');

        return response()->json($this->workspace->post($p, $actor, $id));
    }

    public function reverse(Request $r, int $id): mixed
    {
        [$p, $actor] = $this->scope($r, 'REVERSE');

        return response()->json($this->workspace->reverse($p, $actor, $id, $r->all()));
    }

    public function delete(Request $r, int $id): mixed
    {
        [$p, $actor] = $this->scope($r, 'UPDATE');
        $this->workspace->deleteDraft($p, $actor, $id);

        return response()->noContent();
    }

    public function ledger(Request $r): mixed
    {
        [$p] = $this->scope($r);
        [$from, $to, $page, $search] = $this->range($r);
        $v = $r->validate(['account_id' => 'required|integer|min:1']);

        return response()->json($this->reports->ledger($p, (int) $v['account_id'], $from, $to, $search, $page));
    }

    public function audit(Request $r): mixed
    {
        [$p] = $this->scope($r);

        return response()->json($this->workspace->audit($p));
    }

    public function period(Request $r): mixed
    {
        [$p, $actor] = $this->scope($r, 'ADMIN');
        $v = $r->validate(['period' => 'required|string', 'closed' => 'required|boolean', 'reason' => 'required|string|min:10|max:2000']);
        GstData::period($v['period']);
        $this->workspace->write($p, function () use ($p, $actor, $v) {
            DB::table('mm_accounting_periods')->updateOrInsert(['plant_id' => $p, 'period' => $v['period']], ['closed' => $v['closed']]);
            $this->workspace->event($p, $actor, 'period.changed', $v);
        });

        return response()->noContent();
    }

    public function export(Request $r): mixed
    {
        [$p] = $this->scope($r, 'EXPORT');
        [$from, $to, , $search] = $this->range($r);
        $v = $r->validate(['report' => 'required|in:trial,profit,balance,ledger,journals', 'account_id' => 'required_if:report,ledger|integer|min:1']);
        if ($v['report'] === 'ledger') {
            abort_unless(DB::table('mm_ledgers')->where('plant_id', $p)->where('id', $v['account_id'])->exists(), 404);
        }

        return response()->streamDownload(function () use ($p, $from, $to, $search, $v) {
            $out = fopen('php://output', 'w');
            $csv = function (array $row) use ($out) {
                fputcsv($out, array_map(fn ($x) => is_string($x) && preg_match('/^[\s=+@-]/', $x) && ! preg_match('/^-?\d+(?:\.\d+)?$/D', $x) ? "'".$x : $x, $row), ',', '"', '');
            };
            if (in_array($v['report'], ['trial', 'profit', 'balance'])) {
                $s = $this->reports->summary($p, $from, $to);
                $csv(['plant_id', 'from', 'to', 'account_id', 'code', 'name', 'type', 'opening', 'debit', 'credit', 'closing']);
                foreach ($s['accounts'] as $a) {
                    if ($v['report'] === 'profit' && ! in_array($a['type'], ['income', 'expense'])) {
                        continue;
                    }
                    $csv([$p, $from, $to, $a['account_id'], $a['code'], $a['title'], $a['type'], $a['opening'], $a['debit'], $a['credit'], $a['closing']]);
                }
            } else {
                $csv($v['report'] === 'ledger' ? ['date', 'voucher', 'reference', 'narration', 'debit', 'credit', 'running_balance'] : ['id', 'date', 'voucher', 'reference', 'narration', 'status', 'debit', 'credit']);
                $page = 1;
                do {
                    $r = $v['report'] === 'ledger' ? $this->reports->ledger($p, (int) $v['account_id'], $from, $to, $search, $page) : $this->reports->journals($p, $from, $to, $search, $page)['rows']->toArray();
                    foreach (($r['rows'] ?? $r['data']) as $row) {
                        $row = (array) $row;
                        $csv($v['report'] === 'ledger' ? [$row['voucher_date'], $row['voucher_number'], $row['ref_name'], $row['narration'], $row['debit_amount'], $row['credit_amount'], $row['running_balance']] : [$row['id'], $row['voucher_date'], $row['voucher_number'], $row['ref_name'], $row['narration'], $row['is_status'], $row['total_debit'], $row['total_credit']]);
                    }
                    $page++;
                } while (($page - 1) * 50 < $r['total']);
            }
            fclose($out);
        }, 'accounting-plant-'.$p.'-'.$v['report'].'-'.$to.'.csv', ['Content-Type' => 'text/csv', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function tallyConfig(Request $r, AccountingTally $tally): mixed
    {
        [$p, $actor] = $this->scope($r, 'ADMIN');
        $tally->configure($p, $actor, $r->all());

        return response()->noContent();
    }

    public function tallyConnection(Request $r, AccountingTally $tally): mixed
    {
        [$p, $actor] = $this->scope($r, 'TALLY');

        return response()->json($tally->connection($p, $actor));
    }

    public function tallyEnqueue(Request $r, int $id, AccountingTally $tally): mixed
    {
        [$p, $actor] = $this->scope($r, 'TALLY');
        $j = $tally->enqueue($p, $actor, $id);
        unset($j->xml, $j->lease);

        return response()->json($j);
    }

    public function tallySend(Request $r, int $id, AccountingTally $tally): mixed
    {
        [$p, $actor] = $this->scope($r, 'TALLY');
        $j = $tally->send($p, $actor, $id);
        unset($j->xml, $j->lease);

        return response()->json($j);
    }

    public function tallyResolve(Request $r, int $id, AccountingTally $tally): mixed
    {
        [$p, $actor] = $this->scope($r, 'TALLY');
        $j = $tally->resolve($p, $actor, $id, $r->all());
        unset($j->xml, $j->lease);

        return response()->json($j);
    }
}
