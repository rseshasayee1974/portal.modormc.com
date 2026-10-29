<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:42:34 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Reports;

use App\Services\PlantContextService;
use Illuminate\Support\Facades\DB;

class CreditDebitNoteRegisterService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $type = strtolower((string) ($params['voucher_type_filter'] ?? ''));
        $types = in_array($type, ['credit_note', 'debit_note'], true) ? [$type] : ['credit_note', 'debit_note'];
        $notes = DB::table('mm_invoices as note')
            ->leftJoin('mm_patrons as partner', function ($join) use ($plantId) {
                $join->on('partner.id', '=', 'note.partner_id')->where('partner.plant_id', $plantId);
            })
            ->leftJoin('mm_invoices as source', function ($join) use ($plantId) {
                $join->on('source.id', '=', 'note.ref_id')->where('source.plant_id', $plantId);
            })
            ->where('note.plant_id', $plantId)->whereNull('note.deleted_at')
            ->whereIn(DB::raw('LOWER(note.invoice_type)'), $types)
            ->whereNotIn(DB::raw('LOWER(COALESCE(note.status, \'\'))'), ['cancelled', 'canceled', 'void', 'reversed'])
            ->whereDate('note.invoice_date', '>=', substr($params['start'], 0, 10))
            ->whereDate('note.invoice_date', '<=', substr($params['end'], 0, 10))
            ->when($params['patron_id'] ?? null, fn ($query, $id) => $query->where('note.partner_id', $id))
            ->orderBy('note.invoice_date')->orderBy('note.id')
            ->select('note.*', 'partner.legal_name as partner_name', 'partner.gstin as partner_gstin',
                'source.prefix as source_prefix', 'source.invoice_number as source_number', 'source.invoice_date as source_date')
            ->get();
        $rows = [];
        $credit = 0;
        $debit = 0;
        foreach ($notes as $note) {
            $creditNote = strtolower($note->invoice_type) === 'credit_note';
            $amount = round((float) $note->total_amount, 2);
            if ($creditNote) $credit += $amount;
            else $debit += $amount;
            $rows[] = [
                'id' => $note->id, 'date' => substr($note->invoice_date, 0, 10),
                'note_number' => $this->number($note->prefix, $note->invoice_number),
                'note_type' => $creditNote ? 'Credit Note' : 'Debit Note',
                'partner_id' => $note->partner_id, 'partner_name' => $note->partner_name ?? '—',
                'gstin' => $note->partner_gstin ?? '',
                'source_number' => $note->source_number !== null ? $this->number($note->source_prefix, $note->source_number) : ($note->ref_title ?? '—'),
                'source_date' => $note->source_date ? substr($note->source_date, 0, 10) : '',
                'subtotal_amt' => round((float) $note->subtotal, 2),
                'discount_amt' => round((float) $note->discount_total, 2),
                'tax_amt' => round((float) $note->tax_amount, 2), 'total_amt' => $amount,
                'status' => ucfirst(strtolower((string) $note->status)), 'reason' => $note->notes ?? '',
            ];
        }
        $totals = [];
        foreach (['subtotal_amt', 'discount_amt', 'tax_amt', 'total_amt'] as $field) {
            $totals[$field] = round(array_sum(array_column($rows, $field)), 2);
        }
        return [
            'transactions' => $rows, 'total_count' => count($rows),
            'credit_note_amount' => round($credit, 2), 'debit_note_amount' => round($debit, 2),
            'total_amount' => $totals['total_amt'], 'totals' => $totals,
            'headers' => ['Date', 'Note No.', 'Type', 'Partner', 'GSTIN', 'Original Invoice / Bill', 'Original Date', 'Subtotal (₹)', 'Discount (₹)', 'Tax (₹)', 'Total (₹)', 'Status'],
            'fields' => ['date', 'note_number', 'note_type', 'partner_name', 'gstin', 'source_number', 'source_date', 'subtotal_amt', 'discount_amt', 'tax_amt', 'total_amt', 'status'],
            'alignments' => ['center', 'left', 'left', 'left', 'left', 'left', 'center', 'right', 'right', 'right', 'right', 'center'],
        ];
    }

    private function number(?string $prefix, ?string $number): string
    {
        $prefix ??= '';
        $number = trim($number ?? '');
        if ($prefix !== '' && str_starts_with($number, $prefix)) $number = substr($number, strlen($prefix));
        return $prefix.$number;
    }

    public function targetName(array $params): string
    {
        return 'Credit Note & Debit Note Register';
    }
}
