<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\{JournalEntry, Ledger, OpeningBalanceBatch, Patron};
use App\Services\{OpeningBalanceService, PlantContextService};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OpeningBalanceController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'opening_balance';

    public function __construct(private PlantContextService $context, private OpeningBalanceService $service) {}

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = $this->context->requirePlantId();
        return Inertia::render('OpeningBalances/Index', [
            'records' => OpeningBalanceBatch::where('plant_id', $plantId)->whereNotNull('active_key')->orderByDesc('id')->get(),
            'legacy' => app(\App\Services\IndividualOpeningBalanceService::class)->legacy($plantId)->first(),
            'ledgers' => Ledger::where('plant_id', $plantId)->where('status',1)->orderBy('title')->get(['id','code','title','is_pnl']),
            'patrons' => Patron::where('plant_id',$plantId)->orderBy('legal_name')->get(['id','code','legal_name','debit_ledger_id','credit_ledger_id']),
        ]);
    }

    public function exportAudit()
    {
        $this->authorizeModule('audit_log');
        $plantId = $this->context->requirePlantId();
        $logs = \App\Models\OpeningBalanceAuditLog::where('plant_id', $plantId)->orderBy('id');
        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $write = function (array $row) use ($out) {
                // Keep user-entered notes/references as text in spreadsheet applications.
                $row = array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@-]/', $value) ? "'".$value : $value, $row);
                fputcsv($out, $row, ',', '"', '');
            };
            $write(['Audit ID', 'Plant ID', 'Record ID', 'Replaces Record ID', 'Action', 'Date / Time', 'User ID', 'User', 'Version', 'Reason',
                'Snapshot', 'Status', 'Opening Date', 'Clearing Ledger ID', 'Notes', 'Ledger ID', 'Patron ID', 'Side', 'Amount', 'Reference',
                'Journal ID', 'Voucher Number', 'Reversal Of Journal ID', 'Journal Total Debit', 'Journal Total Credit']);
            foreach ($logs->lazy(100) as $log) {
                $journal = $log->journal ?? [];
                $prefix = [$log->id, $log->plant_id, $log->batch_id, $log->after_values['replaces_id'] ?? null, $log->action, $log->created_at?->format('Y-m-d H:i:s'), $log->user_id, $log->actor_name, $log->version, $log->reason];
                $suffix = [$journal['id'] ?? null, $journal['voucher_number'] ?? null, $journal['reversal_of_id'] ?? null, $journal['total_debit'] ?? null, $journal['total_credit'] ?? null];
                foreach (['Before' => $log->before_values, 'After' => $log->after_values] as $label => $snapshot) {
                    if (!$snapshot) continue;
                    foreach (($snapshot['lines'] ?? []) ?: [[]] as $line) {
                        $write(array_merge($prefix, [$label, $snapshot['status'] ?? null, substr($snapshot['cutover_date'] ?? '', 0, 10), $snapshot['clearing_account_id'] ?? null,
                            $snapshot['notes'] ?? null, $line['account_id'] ?? null, $line['partner_id'] ?? null, $line['side'] ?? null, $line['amount'] ?? null, $line['reference'] ?? null], $suffix));
                    }
                }
                foreach ($journal['lines'] ?? [] as $line) {
                    $debit = (float) ($line['debit_amount'] ?? 0);
                    $write(array_merge($prefix, ['Journal', $journal['is_status'] ?? null, substr($journal['voucher_date'] ?? '', 0, 10), null, $journal['narration'] ?? null,
                        $line['account_id'] ?? null, $line['partner_id'] ?? null, $debit > 0 ? 'Dr' : 'Cr', $debit > 0 ? $line['debit_amount'] : ($line['credit_amount'] ?? 0), $line['line_narration'] ?? null], $suffix));
                }
            }
            fclose($out);
        }, 'Opening_Balance_Audit_Plant_'.$plantId.'_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function save(Request $request)
    {
        $request->validate(['record_id' => 'nullable|integer|min:1']);
        $this->authorizeModule($request->filled('record_id') ? 'edit' : 'create');
        $record = app(\App\Services\IndividualOpeningBalanceService::class)->save($request->all(), $this->context->requirePlantId(), auth()->id());
        return response()->json(['record'=>$record]);
    }

    public function remove(Request $request, int $record)
    {
        $this->authorizeModule('delete');
        $data=$request->validate(['version'=>'required|integer|min:1','reason'=>'required|string|min:5|max:1000']);
        app(\App\Services\IndividualOpeningBalanceService::class)->remove($this->context->requirePlantId(),auth()->id(),$record,$data['version'],$data['reason']);
        return response()->json(['message'=>'Opening balance removed.']);
    }

    public function convert(Request $request)
    {
        $this->authorizeModule('create');
        $data=$request->validate(['clearing_account_id'=>'required|integer']);
        app(\App\Services\IndividualOpeningBalanceService::class)->convert($this->context->requirePlantId(),auth()->id(),$data['clearing_account_id']);
        return response()->json(['message'=>'Existing balances converted without changing their amounts.']);
    }

    public function template()
    {
        $this->authorizeModule('menu');
        $this->context->requirePlantId();
        return response("ledger_code,patron_code,side,amount,reference\r\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="opening-balances-template.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $this->authorizeModule('create');
        $plantId = $this->context->requirePlantId();
        $request->validate(['file' => 'required|file|max:2048', 'cutover_date' => 'required|date_format:Y-m-d']);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if ($header) {
                $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
                $header = array_map('trim', $header);
            }
            if ($header !== ['ledger_code', 'patron_code', 'side', 'amount', 'reference']) {
                throw ValidationException::withMessages(['file' => 'Use the CSV template with ledger_code,patron_code,side,amount,reference columns in that order.']);
            }
            $ledgers = Ledger::where('plant_id', $plantId)->get()->groupBy('code');
            $patrons = Patron::where('plant_id', $plantId)->get()->groupBy('code');
            $lines = [];
            $row = 1;
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $row++;
                if ($values === [null]) continue;
                if (count($values) !== 5 || count($lines) >= 2000) {
                    throw ValidationException::withMessages(['file' => "Row $row: expected 5 columns, with at most 2,000 balance rows."]);
                }
                [$ledgerCode, $patronCode, $side, $amount, $reference] = array_map('trim', $values);
                if (!$ledgers->has($ledgerCode) || $ledgers[$ledgerCode]->count() !== 1
                    || ($patronCode !== '' && (!$patrons->has($patronCode) || $patrons[$patronCode]->count() !== 1))) {
                    throw ValidationException::withMessages(['file' => "Row $row: unknown or ambiguous ledger/patron code for this plant."]);
                }
                $lines[] = ['account_id' => $ledgers[$ledgerCode]->first()->id,
                    'partner_id' => $patronCode !== '' ? $patrons[$patronCode]->first()->id : null,
                    'side' => ucfirst(strtolower($side)), 'amount' => $amount, 'reference' => $reference];
            }
            $data = $this->service->validate([
                'cutover_date' => $request->cutover_date,
                'clearing_account_id' => $request->clearing_account_id,
                'lines' => $lines,
            ], $plantId);
            // Preview only: importing never saves or posts accounting entries.
            return response()->json(['lines' => $data['lines']]);
        } finally {
            fclose($handle);
        }
    }
}
