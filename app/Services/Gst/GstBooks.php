<?php

/*
Author: ragul-onemodo
Created: 2026-10-17 14:20:53 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Gst;

use App\Support\InvoiceClassification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class GstBooks
{
    public function ledgerHash(int $plant, string $period): ?string
    {
        if (! Schema::hasTable('mm_journal_entries') || ! Schema::hasTable('mm_journal_entry_lines')) {
            return null;
        }
        $headers = DB::table('mm_journal_entries')->where('plant_id', $plant)->whereDate('voucher_date', '>=', $period.'-01')
            ->whereDate('voucher_date', '<=', date('Y-m-t', strtotime($period.'-01')))->orderBy('id')->get();
        $lines = DB::table('mm_journal_entry_lines')->whereIn('journal_entry_id', $headers->pluck('id'))->orderBy('id')->get();

        return GstData::hash(json_decode(json_encode(['headers' => $headers, 'lines' => $lines], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
    }

    /** Finalised invoices/bills only. A purchase order is not itself an ITC entitlement. */
    public function period(int $plant, string $period): array
    {
        GstData::period($period);

        return $this->range($plant, $period.'-01', date('Y-m-t', strtotime($period.'-01')));
    }

    public function range(int $plant, string $from, string $to): array
    {
        $documents = DB::table('mm_invoices as i')->leftJoin('mm_patrons as p', function ($j) {
            $j->on('p.id', '=', 'i.partner_id')->on('p.plant_id', '=', 'i.plant_id')->whereNull('p.deleted_at');
        })
            ->leftJoin('mm_invoices as source', function ($j) {
                $j->on('source.id', '=', 'i.ref_id')->on('source.plant_id', '=', 'i.plant_id');
            })
            ->where('i.plant_id', $plant)->whereNull('i.deleted_at')->whereRaw("LOWER(COALESCE(i.status,'')) IN ('approved','paid')")
            ->whereIn('i.invoice_type', array_merge(InvoiceClassification::aliases('Invoice'), InvoiceClassification::aliases('Bill'), ['credit_note', 'debit_note']))
            ->whereDate('i.invoice_date', '>=', $from)->whereDate('i.invoice_date', '<=', $to)
            ->select('i.*', 'p.gstin as supplier_gstin', 'p.legal_name as partner_name', 'source.invoice_type as source_type')->orderBy('i.id')->get();
        $ids = $documents->pluck('id')->all();
        $taxes = $ids ? DB::table('mm_order_taxes')->where('plant_id', $plant)->whereIn('order_id', $ids)->whereNull('deleted_at')->orderBy('id')->get()->groupBy(fn ($r) => $r->order_type.':'.$r->order_id) : collect();
        $out = [];
        foreach ($documents as $d) {
            $type = strtolower($d->invoice_type);
            $base = in_array($type, ['credit_note', 'debit_note'], true) ? strtolower($d->source_type ?? '') : $type;
            $direction = in_array($base, ['purchase', 'bill'], true) ? 'purchase' : 'sale';
            $negative = ($direction === 'sale' && $type === 'credit_note') || ($direction === 'purchase' && $type === 'debit_note');
            $sign = $negative ? -1 : 1;
            $amounts = array_fill_keys(GstData::HEADS, 0);
            $amounts['taxable'] = $sign * abs(GstData::paise($d->subtotal));
            $issues = [];
            $module = $direction === 'purchase' ? 'Purchase' : 'Invoice';
            $taxRows = $taxes->get($module.':'.$d->id, collect());
            foreach ($taxRows as $tax) {
                $name = strtoupper($tax->name ?? '');
                $head = match (true) {
                    str_contains($name, 'CGST') => 'cgst',str_contains($name, 'SGST') || str_contains($name, 'UGST') || str_contains($name, 'UTGST') => 'sgst',str_contains($name, 'IGST') => 'igst',str_contains($name, 'CESS') => 'cess',default => null
                };
                if ($head) {
                    $amounts[$head] += $sign * abs(GstData::paise($tax->amount));
                } elseif (GstData::paise($tax->amount) !== 0) {
                    $issues[] = 'Unclassified tax: '.$tax->name;
                }
            }
            if (abs(array_sum(array_diff_key($amounts, ['taxable' => true]))) !== abs(GstData::paise($d->tax_amount))) {
                $issues[] = 'Stored GST splits do not equal document tax; verify source before classification';
            }
            if (in_array($type, ['credit_note', 'debit_note'], true) && ! $d->source_type) {
                $issues[] = 'Note source document is missing';
            }
            if ($direction === 'purchase' && $type === 'debit_note') {
                $issues[] = 'Local debit note: verify supplier credit-note identity and reversal treatment';
            }
            $number = trim((string) $d->invoice_number);
            $prefix = $d->prefix ?? '';
            if ($prefix && ! str_starts_with($number, $prefix)) {
                $number = rtrim($prefix, '/').'/'.ltrim($number, '/');
            }
            $row = ['source_key' => 'invoice:'.$d->id, 'document_id' => $d->id, 'direction' => $direction, 'number' => $number, 'date' => substr($d->invoice_date, 0, 10),
                'kind' => match ($type) {
                    'credit_note' => 'CN','debit_note' => 'DN',default => 'INV'
                }, 'supplier_gstin' => trim($d->supplier_gstin ?? ''),
                'partner_name' => $d->partner_name ?? '', 'amounts' => $amounts, 'reverse_charge' => null, 'issues' => array_values(array_unique($issues)),
                'source_digest' => GstData::hash(['document' => (array) $d, 'taxes' => $taxRows->map(fn ($r) => (array) $r)->all()])];
            $row['hash'] = GstData::hash($row);
            $out[] = $row;
        }

        return $out;
    }
}
