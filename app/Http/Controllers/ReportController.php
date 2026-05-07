<?php

namespace App\Http\Controllers;

use App\Models\JournalEntryLine;
use App\Models\Ledger;
use App\Models\Patron;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $plantId = session('active_plant_id');
        $ledgers = Ledger::where('plant_id', $plantId)->orderBy('title')->get();
        $patrons = Patron::where('plant_id', $plantId)->orderBy('legal_name')->get();

        return Inertia::render('Finance/Reports/Index', [
            'ledgers' => $ledgers,
            'patrons' => $patrons,
            'filters' => [
                'start_date' => $request->input('start_date', now()->startOfMonth()->toDateString()),
                'end_date' => $request->input('end_date', now()->toDateString()),
            ]
        ]);
    }

    public function generate(Request $request)
    {
        $type = $request->input('type');
        $id = $request->input('id');
        $patronId = $request->input('patron_id');
        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $export = $request->input('export');
        $plantId = session('active_plant_id');

        switch ($type) {
            case 'ledger':
                $data = $this->getLedgerReportData($id, $patronId, $start, $end);
                $targetName = $id ? Ledger::find($id)->title : 'All Ledgers Consolidated';
                break;
            case 'patron':
                $data = $this->getPatronReportData($patronId, $start, $end);
                $targetName = $patronId ? Patron::find($patronId)->legal_name : 'All Patrons / Global Summary';
                break;
            case 'purchase':
                $data = $this->getPurchaseReportData($patronId, $start, $end);
                $targetName = $patronId ? Patron::find($patronId)->legal_name : 'All Vendor Purchases';
                break;
            case 'sales':
                $data = $this->getSalesReportData($patronId, $start, $end);
                $targetName = $patronId ? Patron::find($patronId)->legal_name : 'All Customer Sales';
                break;
            case 'payment':
                $data = $this->getVoucherReportData('PAYMENT', $patronId, $start, $end);
                $targetName = $patronId ? Patron::find($patronId)->legal_name : 'All Payment Vouchers';
                break;
            case 'receipt':
                $data = $this->getVoucherReportData('RECEIPT', $patronId, $start, $end);
                $targetName = $patronId ? Patron::find($patronId)->legal_name : 'All Receipt Vouchers';
                break;
            default:
                return response()->json(['error' => 'Invalid report type'], 400);
        }

        if ($export === 'excel') {
            return $this->exportExcel($type, $start, $end, $data);
        }

        if ($export === 'pdf') {
            return $this->exportPdf($type, $targetName, $start, $end, $data);
        }

        return response()->json($data);
    }

    private function getLedgerReportData($ledgerId, $patronId, $start, $end)
    {
        $plantId = session('active_plant_id');
        $query = JournalEntryLine::where('plant_id', $plantId);
        
        if ($ledgerId) $query->where('account_id', $ledgerId);
        if ($patronId) $query->where('partner_id', $patronId)->where('partner_type', 'Patron');

        $openingBalance = (clone $query)
            ->whereHas('entry', function($q) use ($start) {
                $q->where('voucher_date', '<', $start);
            })
            ->selectRaw('SUM(debit_amount) - SUM(credit_amount) as balance')
            ->value('balance') ?: 0;

        $transactions = $query->with(['entry.lines.ledger', 'entry.lines.partner', 'ledger'])
            ->whereHas('entry', function($q) use ($start, $end) {
                $q->whereBetween('voucher_date', [$start, $end]);
            })
            ->get()
            ->sortBy(fn($line) => $line->entry->voucher_date . $line->entry->id)
            ->map(function($line) use ($ledgerId) {
                $isDebit = $line->debit_amount > 0;
                $oppositeLines = $line->entry->lines->filter(fn($l) => $l->id != $line->id);
                $particulars = '';
                
                if ($oppositeLines->count() == 1) {
                    $opp = $oppositeLines->first();
                    $particulars = ($isDebit ? 'To ' : 'By ') . ($opp->ledger->title ?? $opp->partner->legal_name ?? 'Unknown');
                } else {
                    $particulars = ($isDebit ? 'To ' : 'By ') . "As per details";
                }

                // If viewing All Ledgers, prepend the ledger name to particulars
                $ledgerNamePrefix = (!$ledgerId) ? '[' . ($line->ledger->title ?? 'N/A') . '] ' : '';

                return [
                    'date' => $line->entry->voucher_date->toDateString(),
                    'voucher_type' => $line->entry->voucher_type,
                    'voucher_no' => $line->entry->voucher_number,
                    'narration' => $ledgerNamePrefix . $particulars . ' (' . ($line->line_narration ?: $line->entry->narration) . ')',
                    'amount' => $isDebit ? (float)$line->debit_amount : (float)$line->credit_amount,
                    'type' => $isDebit ? 'Dr' : 'Cr',
                    'debit' => (float)$line->debit_amount,
                    'credit' => (float)$line->credit_amount,
                ];
            })->values();

        return ['opening_balance' => (float)$openingBalance, 'transactions' => $transactions];
    }

    private function getPatronReportData($patronId, $start, $end)
    {
        $plantId = session('active_plant_id');
        $query = JournalEntryLine::where('plant_id', $plantId);
        if ($patronId) $query->where('partner_id', $patronId)->where('partner_type', 'Patron');

        $openingBalance = (clone $query)
            ->whereHas('entry', function($q) use ($start) {
                $q->where('voucher_date', '<', $start);
            })
            ->selectRaw('SUM(debit_amount) - SUM(credit_amount) as balance')
            ->value('balance') ?: 0;

        $transactions = $query->with(['entry.lines.ledger', 'partner'])
            ->whereHas('entry', function($q) use ($start, $end) {
                $q->whereBetween('voucher_date', [$start, $end]);
            })
            ->get()
            ->sortBy(fn($line) => $line->entry->voucher_date . $line->entry->id)
            ->map(function($line) {
                $isDebit = $line->debit_amount > 0;
                $oppositeLines = $line->entry->lines->filter(fn($l) => $l->id != $line->id);
                $particulars = '';
                if ($oppositeLines->count() == 1) {
                    $opp = $oppositeLines->first();
                    $particulars = ($isDebit ? 'To ' : 'By ') . ($opp->ledger->title ?? 'General Account');
                } else {
                    $particulars = ($isDebit ? 'To ' : 'By ') . "As per details";
                }

                $patronNamePrefix = (!$line->partner_id) ? '[' . ($line->partner->legal_name ?? 'N/A') . '] ' : '';

                return [
                    'date' => $line->entry->voucher_date->toDateString(),
                    'voucher_type' => $line->entry->voucher_type,
                    'voucher_no' => $line->entry->voucher_number,
                    'narration' => $patronNamePrefix . $particulars . ' (' . ($line->line_narration ?: $line->entry->narration) . ')',
                    'amount' => $isDebit ? (float)$line->debit_amount : (float)$line->credit_amount,
                    'type' => $isDebit ? 'Dr' : 'Cr',
                    'debit' => (float)$line->debit_amount,
                    'credit' => (float)$line->credit_amount,
                ];
            })->values();

        return ['opening_balance' => (float)$openingBalance, 'transactions' => $transactions];
    }

    private function getPurchaseReportData($patronId, $start, $end)
    {
        $plantId = session('active_plant_id');
        $query = PurchaseOrder::with(['vendor'])->where('plant_id', $plantId)->whereBetween('date_order', [$start, $end]);
        if ($patronId) $query->where('vendor_id', $patronId);

        $bills = $query->get()->map(fn($po) => [
            'date' => $po->date_order->toDateString(),
            'voucher_type' => 'PURCHASE',
            'voucher_no' => $po->po_number,
            'narration' => '[' . ($po->vendor->legal_name ?? 'Vendor') . '] Purchase Bill',
            'amount' => (float)$po->amount_total,
            'type' => 'Dr',
            'debit' => (float)$po->amount_total,
            'credit' => 0,
        ]);

        return ['opening_balance' => 0, 'transactions' => $bills];
    }

    private function getSalesReportData($patronId, $start, $end)
    {
        $plantId = session('active_plant_id');
        $query = Invoice::with(['partner'])->where('plant_id', $plantId)->whereBetween('invoice_date', [$start, $end]);
        if ($patronId) $query->where('partner_id', $patronId);

        $invoices = $query->get()->map(fn($inv) => [
            'date' => $inv->invoice_date->toDateString(),
            'voucher_type' => 'SALES',
            'voucher_no' => $inv->invoice_number,
            'narration' => '[' . ($inv->partner->legal_name ?? 'Customer') . '] Sales Invoice',
            'amount' => (float)$inv->total_amount,
            'type' => 'Cr',
            'debit' => 0,
            'credit' => (float)$inv->total_amount,
        ]);

        return ['opening_balance' => 0, 'transactions' => $invoices];
    }

    private function getVoucherReportData($voucherType, $patronId, $start, $end)
    {
        $plantId = session('active_plant_id');
        $query = JournalEntryLine::where('plant_id', $plantId)
            ->whereHas('entry', function($q) use ($voucherType, $start, $end) {
                $q->where('voucher_type', $voucherType)->whereBetween('voucher_date', [$start, $end]);
            });
            
        if ($patronId) $query->where('partner_id', $patronId)->where('partner_type', 'Patron');

        $transactions = $query->with(['entry', 'ledger', 'partner'])->get()->map(function($line) {
            $isDebit = $line->debit_amount > 0;
            $partnerPrefix = $line->partner ? '[' . $line->partner->legal_name . '] ' : '[' . ($line->ledger->title ?? 'N/A') . '] ';
            return [
                'date' => $line->entry->voucher_date->toDateString(),
                'voucher_type' => $line->entry->voucher_type,
                'voucher_no' => $line->entry->voucher_number,
                'narration' => $partnerPrefix . ($line->line_narration ?: $line->entry->narration),
                'amount' => $isDebit ? (float)$line->debit_amount : (float)$line->credit_amount,
                'type' => $isDebit ? 'Dr' : 'Cr',
                'debit' => (float)$line->debit_amount,
                'credit' => (float)$line->credit_amount,
            ];
        });

        return ['opening_balance' => 0, 'transactions' => $transactions];
    }

    private function exportPdf($type, $targetName, $start, $end, $data)
    {
        $viewMap = [
            'LEDGER' => 'reports.ledger_report',
            'PATRON' => 'reports.patron_report',
            'SALES' => 'reports.sales_report',
            'PURCHASE' => 'reports.purchase_report',
            'PAYMENT' => 'reports.payment_report',
            'RECEIPT' => 'reports.receipt_report',
        ];

        $view = $viewMap[strtoupper($type)] ?? 'reports.ledger_report';

        $pdf = Pdf::loadView($view, [
            'type' => strtoupper($type),
            'target_name' => $targetName,
            'start' => $start,
            'end' => $end,
            'opening_balance' => $data['opening_balance'],
            'transactions' => $data['transactions']
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Report_{$type}_{$start}.pdf");
    }

    private function exportExcel($type, $start, $end, $data)
    {
        $filename = "Report_{$type}_{$start}_to_{$end}.csv";
        $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];

        return response()->stream(function() use ($type, $start, $end, $data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ["Report Type:", strtoupper($type)]);
            fputcsv($file, ["Period:", "$start to $end"]);
            fputcsv($file, []);
            fputcsv($file, ['Date', 'Particulars', 'Voucher Type', 'Voucher No', 'Amount', 'Type', 'Balance']);

            $balance = $data['opening_balance'] ?? 0;
            if ($balance != 0) fputcsv($file, [$start, 'Opening Balance', '', '', abs($balance), $balance > 0 ? 'Dr' : 'Cr', $balance]);

            foreach ($data['transactions'] as $row) {
                $balance += ($row['debit'] - $row['credit']);
                fputcsv($file, [$row['date'], $row['narration'], $row['voucher_type'], $row['voucher_no'], $row['amount'], $row['type'], $balance]);
            }
            fclose($file);
        }, 200, $headers);
    }
}
