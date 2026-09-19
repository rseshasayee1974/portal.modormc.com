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

    protected string $module = 'journal_entry';

    public function __construct(private PlantContextService $context, private OpeningBalanceService $service) {}

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = $this->context->requirePlantId();
        $batch = OpeningBalanceBatch::where('plant_id', $plantId)->first();
        return Inertia::render('OpeningBalances/Index', [
            'batch' => $batch,
            'ledgers' => Ledger::where('plant_id', $plantId)->where('status', 1)->orderBy('title')->get(['id', 'code', 'title', 'is_pnl']),
            'patrons' => Patron::where('plant_id', $plantId)->orderBy('legal_name')->get(['id', 'code', 'legal_name', 'debit_ledger_id', 'credit_ledger_id']),
            'history' => $batch ? JournalEntry::where('plant_id', $plantId)->where('ref_id', $batch->id)
                ->whereIn('ref_module', ['opening_balance', 'opening_balance_reversal'])
                ->orderByDesc('id')->get(['id', 'voucher_number', 'voucher_date', 'narration', 'total_debit', 'total_credit', 'created_at']) : [],
        ]);
    }

    public function save(Request $request)
    {
        $this->authorizeModule('create');
        $request->validate(['version' => 'required|integer|min:0']);
        return response()->json(['batch' => $this->service->save($request->all(), $this->context->requirePlantId(), auth()->id(), (int) $request->version)]);
    }

    public function post(Request $request)
    {
        $this->authorizeModule('create');
        $request->validate(['version' => 'required|integer|min:1']);
        return response()->json(['batch' => $this->service->post($this->context->requirePlantId(), auth()->id(), (int) $request->version)]);
    }

    public function reverse(Request $request)
    {
        $this->authorizeModule('delete');
        $request->validate(['version' => 'required|integer|min:1', 'reason' => 'required|string|min:5|max:1000']);
        return response()->json(['batch' => $this->service->reverse($this->context->requirePlantId(), auth()->id(), (int) $request->version, $request->reason)]);
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
