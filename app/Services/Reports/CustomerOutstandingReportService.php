<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use App\Models\Patron;
use App\Models\Plant;
use App\Services\PlantContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerOutstandingReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId  = $params['plant_id'] ?? $this->ctx->plantId() ?? session('active_plant_id');
        $patronId = $params['patron_id'] ?? null;
        $start    = $params['start'] ?? null;
        $end      = $params['end'] ?? null;

        $query = Invoice::query()
            ->with([
                'partner' => function ($q) {
                    $q->select('id', 'code', 'legal_name', 'gstin', 'pan_no')
                      ->with(['contacts:id,patron_id,name,mobile,alt_mobile,landline,email,is_primary']);
                }
            ])
            ->where('invoice_type', 'sales')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', '!=', 'Cancelled');
            });

        if ($plantId) {
            $query->where('plant_id', $plantId);
        }

        if ($patronId) {
            $query->where('partner_id', $patronId);
        }

        // Include invoices in range or any invoice prior that still has pending balance
        if ($start && $end) {
            $query->where(function ($q) use ($start, $end) {
                $q->whereBetween('invoice_date', [$start, $end])
                  ->orWhere('balance_amount', '>', 0);
            });
        }
        if ($end) {
            $query->where('invoice_date', '<=', $end);
        }

        $invoices = $query->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $today = now()->startOfDay();
        $allOpenInvoices = [];
        $grouped = [];

        foreach ($invoices as $inv) {
            $dueDate = !empty($inv->due_date) 
                ? Carbon::parse($inv->due_date)->startOfDay() 
                : ($inv->invoice_date ? Carbon::parse($inv->invoice_date)->startOfDay() : $today);

            $daysPastDue = (int)$dueDate->diffInDays($today, false);
            $daysOverdue = max(0, $daysPastDue);
            $isOverdue = $daysOverdue > 0;

            $totalAmount   = round((float)($inv->total_amount ?? 0), 2);
            $paidAmount    = round((float)($inv->paid_amount ?? 0), 2);
            $balanceAmount = round(max(0.00, (float)($inv->balance_amount ?? ($totalAmount - $paidAmount))), 2);

            $bracket = match (true) {
                $daysOverdue > 90 => 'aging_90_plus',
                $daysOverdue > 60 => 'aging_61_90',
                $daysOverdue > 30 => 'aging_31_60',
                default           => 'aging_0_30',
            };

            $invItem = [
                'id'             => $inv->id,
                'encrypted_id'   => $inv->encrypted_id,
                'full_number'    => $inv->full_number ?: ('INV-' . $inv->id),
                'prefix'         => $inv->prefix,
                'invoice_number' => $inv->invoice_number,
                'invoice_date'   => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('d/m/Y') : '-',
                'raw_date'       => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('Y-m-d') : '',
                'due_date'       => $inv->due_date ? Carbon::parse($inv->due_date)->format('d/m/Y') : '-',
                'raw_due_date'   => $inv->due_date ? Carbon::parse($inv->due_date)->format('Y-m-d') : '',
                'days_overdue'   => $daysOverdue,
                'is_overdue'     => $isOverdue,
                'aging_bucket'   => $bracket,
                'total_amount'   => $totalAmount,
                'paid_amount'    => $paidAmount,
                'balance_amount' => $balanceAmount,
                'status'         => $balanceAmount <= 0 ? 'Paid' : ($paidAmount > 0 ? 'Partially Paid' : 'Unpaid'),
                'customer_id'    => $inv->partner_id,
                'customer_name'  => $inv->partner?->legal_name ?? 'Unknown Customer',
                'customer_code'  => $inv->partner?->code ?? '-',
            ];

            if ($balanceAmount > 0) {
                $allOpenInvoices[] = $invItem;
            }

            $cId = $inv->partner_id ?: 0;
            if (!isset($grouped[$cId])) {
                $partner = $inv->partner;
                $contacts = $partner?->contacts ?? collect();
                $primaryContact = $contacts->firstWhere('is_primary', 1) ?? $contacts->first();

                $grouped[$cId] = [
                    'customer_id'        => $partner?->id ?? $cId,
                    'customer_code'      => $partner?->code ?? '-',
                    'customer_name'      => $partner?->legal_name ?? 'Unknown Customer',
                    'party_name'         => $partner?->legal_name ?? 'Unknown Customer',
                    'gstin'              => $partner?->gstin ?? '-',
                    'pan_no'             => $partner?->pan_no ?? '-',
                    'contact_person'     => $primaryContact?->name ?? '-',
                    'phone'              => $primaryContact?->mobile ?: ($primaryContact?->alt_mobile ?: ($primaryContact?->landline ?? '-')),
                    'email'              => $primaryContact?->email ?? '-',
                    'total_invoiced'     => 0.0,
                    'total_paid'         => 0.0,
                    'total_outstanding'  => 0.0,
                    'aging_0_30'         => 0.0,
                    'aging_31_60'        => 0.0,
                    'aging_61_90'        => 0.0,
                    'aging_90_plus'      => 0.0,
                    'invoices_count'     => 0,
                    'open_invoices_count'=> 0,
                    'invoices'           => [],
                ];
            }

            $grouped[$cId]['total_invoiced'] += $totalAmount;
            $grouped[$cId]['total_paid']     += $paidAmount;
            $grouped[$cId]['total_outstanding'] += $balanceAmount;
            $grouped[$cId]['invoices_count']++;

            if ($balanceAmount > 0) {
                $grouped[$cId]['open_invoices_count']++;
                $grouped[$cId][$bracket] += $balanceAmount;
            }

            $grouped[$cId]['invoices'][] = $invItem;
        }

        // Format and sort customers: sort by total_outstanding descending
        $customersList = collect(array_values($grouped))->map(function ($row) {
            $row['total_invoiced']    = round($row['total_invoiced'], 2);
            $row['total_paid']        = round($row['total_paid'], 2);
            $row['total_outstanding'] = round($row['total_outstanding'], 2);
            $row['aging_0_30']        = round($row['aging_0_30'], 2);
            $row['aging_31_60']       = round($row['aging_31_60'], 2);
            $row['aging_61_90']       = round($row['aging_61_90'], 2);
            $row['aging_90_plus']     = round($row['aging_90_plus'], 2);
            return $row;
        })->sortByDesc('total_outstanding')->values()->all();

        // Totals
        $totalInvoiced    = round(collect($customersList)->sum('total_invoiced'), 2);
        $totalPaid        = round(collect($customersList)->sum('total_paid'), 2);
        $totalOutstanding = round(collect($customersList)->sum('total_outstanding'), 2);
        $totalAging0to30  = round(collect($customersList)->sum('aging_0_30'), 2);
        $totalAging31to60 = round(collect($customersList)->sum('aging_31_60'), 2);
        $totalAging61to90 = round(collect($customersList)->sum('aging_61_90'), 2);
        $totalAging90Plus = round(collect($customersList)->sum('aging_90_plus'), 2);
        $totalOpenInvCount= (int)collect($customersList)->sum('open_invoices_count');
        $customersWithBal = collect($customersList)->where('total_outstanding', '>', 0)->count();

        $plant = $plantId ? Plant::with(['addresses.state'])->find($plantId) : null;
        $patron = $patronId ? Patron::with(['addresses'])->find($patronId) : null;

        return [
            'transactions'               => $customersList,
            'items'                      => $customersList,
            'customer_summary'           => $customersList,
            'open_invoices'              => $allOpenInvoices,
            'total_customers'            => count($customersList),
            'customers_with_balance'     => $customersWithBal,
            'total_invoiced_amount'      => $totalInvoiced,
            'total_paid_amount'          => $totalPaid,
            'total_outstanding_amount'   => $totalOutstanding,
            'total_amount'               => $totalOutstanding,
            'aging_0_30'                 => $totalAging0to30,
            'aging_31_60'                => $totalAging31to60,
            'aging_61_90'                => $totalAging61to90,
            'aging_90_plus'              => $totalAging90Plus,
            'total_open_invoices'        => $totalOpenInvCount,
            'plant'                      => $plant,
            'patron'                     => $patron,
            'generated_at'               => now()->format('d/m/Y h:i A'),
            'filters'                    => [
                'start'     => $start,
                'end'       => $end,
                'patron_id' => $patronId,
            ],
        ];
    }

    public function targetName(array $params): string
    {
        return isset($params['patron_id']) && $params['patron_id']
            ? (Patron::whereNull('deleted_at')->find($params['patron_id'])?->legal_name ?? 'Customer Outstanding Report')
            : 'All Customers Outstanding Report';
    }
}
