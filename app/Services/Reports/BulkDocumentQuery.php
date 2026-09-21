<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

class BulkDocumentQuery
{
    public function build(int $plantId, array $filters): Builder
    {
        $query = Invoice::query()->where('plant_id', $plantId)
            ->whereRaw("LOWER(invoice_type) IN ('sales', 'bill')")
            ->where(function ($q) { $q->whereNull('status')->orWhereRaw("LOWER(status) NOT IN ('cancelled', 'canceled')"); })
            ->whereDate('invoice_date', '>=', $filters['start_date'])
            ->whereDate('invoice_date', '<=', $filters['end_date']);
        if ($type = $filters['type'] ?? null) {
            $query->whereRaw('LOWER(invoice_type) = ?', [$type === 'invoice' ? 'sales' : 'bill']);
        }
        if ($subtype = $filters['subtype'] ?? null) {
            [$type, $labels] = match ($subtype) {
                'manual_invoice' => ['sales', ['manual', 'tax invoice']],
                'dispatch_invoice' => ['sales', ['dispatch']],
                'manual_bill' => ['bill', ['manual']],
                'vendor_bill' => ['bill', ['purchase', 'vendor bill']],
            };
            $query->whereRaw('LOWER(invoice_type) = ?', [$type])
                ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(invoice_label)'), $labels);
        }
        if ($patron = $filters['patron_id'] ?? null) $query->where('partner_id', $patron);
        if (!empty($filters['invoice_ids'])) {
            $query->whereIn('mm_invoices.id', $filters['invoice_ids']);
        }
        // Match only live journals belonging to this document and plant.
        $journal = function ($q) use ($plantId) {
            $q->selectRaw('1')->from('mm_journal_entries as j')
                ->whereColumn('j.ref_id', 'mm_invoices.id')->where('j.plant_id', $plantId)
                ->whereRaw("j.ref_module = CASE WHEN LOWER(mm_invoices.invoice_type) = 'sales' THEN 'invoice' ELSE 'bill' END")
                ->whereNull('j.deleted_at')->where('j.is_deleted', 0);
        };
        if ($ledger = $filters['ledger_id'] ?? null) {
            $query->where(function ($q) use ($ledger, $journal) {
                $q->where('account_id', $ledger)->orWhereExists(function ($j) use ($ledger, $journal) {
                    $journal($j);
                    $j->join('mm_journal_entry_lines as l', 'l.journal_entry_id', '=', 'j.id')
                        ->where('l.account_id', $ledger)->whereNull('l.deleted_at')->where('l.is_deleted', 0);
                });
            });
        }
        if ($search = trim($filters['reference'] ?? '')) {
            $query->where(function ($q) use ($search, $journal) {
                $q->where('invoice_number', 'like', '%'.$search.'%')
                    ->orWhereRaw("CONCAT(COALESCE(prefix, ''), '/', invoice_number) LIKE ?", ['%'.$search.'%'])
                    ->orWhereExists(function ($j) use ($search, $journal) {
                        $journal($j); $j->where('j.voucher_number', 'like', '%'.$search.'%');
                    });
            });
        }
        if ($tax = $filters['tax_type'] ?? null) {
            if ($tax === 'no_tax') $query->where('tax_amount', 0);
            else {
                $groups = $tax === 'igst' ? ['IGST'] : ['GST', 'CGST', 'SGST'];
                $query->where('tax_amount', '>', 0)->where(function ($q) use ($groups) {
                    $q->whereHas('items.tax', fn($t) => $t->whereIn('tax_group', $groups))
                        ->orWhereHas('orderTaxes.tax', fn($t) => $t->whereIn('tax_group', $groups));
                });
            }
        }
        return $query->orderBy('invoice_date')->orderBy('id');
    }
}
