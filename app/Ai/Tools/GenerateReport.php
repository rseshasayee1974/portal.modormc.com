<?php

namespace App\Ai\Tools;

use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\Invoice;
use Laravel\Ai\Contracts\Tool;
use Stringable;

/**
 * Tool: Generate Report
 *
 * Generates summary reports for batches, dispatches, and invoices.
 */
class GenerateReport implements Tool
{
    public function name(): Stringable|string
    {
        return 'generate_report';
    }

    public function description(): Stringable|string
    {
        return 'Generate summary reports for production batches, dispatches, invoices, or customer activity. Specify report type and date range.';
    }

    public function parameters(): array
    {
        return [
            'report_type' => [
                'type'        => 'string',
                'description' => 'Type of report: batches_summary | dispatches_summary | invoices_summary | customer_summary',
                'required'    => true,
            ],
            'date_from' => [
                'type'        => 'string',
                'description' => 'Report start date (YYYY-MM-DD). Defaults to 30 days ago.',
                'required'    => false,
            ],
            'date_to' => [
                'type'        => 'string',
                'description' => 'Report end date (YYYY-MM-DD). Defaults to today.',
                'required'    => false,
            ],
            'customer_name' => [
                'type'        => 'string',
                'description' => 'Filter by customer name (optional)',
                'required'    => false,
            ],
        ];
    }

    public function handle(
        string $report_type,
        string $date_from = '',
        string $date_to = '',
        string $customer_name = ''
    ): string {
        $plantId  = session('active_plant_id');
        $from     = $date_from ?: now()->subDays(30)->toDateString();
        $to       = $date_to   ?: now()->toDateString();

        return match ($report_type) {
            'batches_summary'    => $this->batchesSummary($plantId, $from, $to, $customer_name),
            'dispatches_summary' => $this->dispatchesSummary($plantId, $from, $to, $customer_name),
            'invoices_summary'   => $this->invoicesSummary($plantId, $from, $to, $customer_name),
            'customer_summary'   => $this->customerSummary($plantId, $from, $to, $customer_name),
            default              => "Unknown report type '{$report_type}'. Use: batches_summary, dispatches_summary, invoices_summary, customer_summary",
        };
    }

    private function batchesSummary(?int $plantId, string $from, string $to, string $customer): string
    {
        $query = Batch::query()->whereBetween('created_at', [$from, $to . ' 23:59:59']);
        if ($plantId) $query->where('plant_id', $plantId);

        $total     = $query->count();
        $completed = (clone $query)->where('status', 3)->count();
        $totalQty  = (clone $query)->sum('batch_size');
        $avgQty    = $total > 0 ? round($totalQty / $total, 2) : 0;

        return <<<REPORT
**Batches Report** ({$from} to {$to})

- **Total Batches:** {$total}
- **Completed:** {$completed}
- **Total Quantity Produced:** {$totalQty} m³
- **Average Batch Size:** {$avgQty} m³
- **Period:** {$from} to {$to}
REPORT;
    }

    private function dispatchesSummary(?int $plantId, string $from, string $to, string $customer): string
    {
        $query = Dispatch::query()->whereBetween('dispatch_time', [$from, $to . ' 23:59:59']);
        if ($plantId) $query->where('plant_id', $plantId);

        $total    = $query->count();
        $totalQty = (clone $query)->sum('delivered_qty');
        $delivered = (clone $query)->where('dispatch_status', 'Delivered')->count();

        return <<<REPORT
**Dispatches Report** ({$from} to {$to})

- **Total Dispatches:** {$total}
- **Delivered:** {$delivered}
- **Total Delivered Quantity:** {$totalQty} m³
- **Period:** {$from} to {$to}
REPORT;
    }

    private function invoicesSummary(?int $plantId, string $from, string $to, string $customer): string
    {
        $query = Invoice::query()->whereBetween('invoice_date', [$from, $to]);
        if ($plantId) $query->where('plant_id', $plantId);

        $total        = $query->count();
        $totalAmount  = (clone $query)->sum('grand_total');
        $totalPaid    = (clone $query)->sum('amount_paid');
        $totalBalance = (clone $query)->sum('balance_due');
        $paid         = (clone $query)->where('payment_status', 'paid')->count();

        $totalAmount  = number_format($totalAmount, 2);
        $totalPaid    = number_format($totalPaid, 2);
        $totalBalance = number_format($totalBalance, 2);

        return <<<REPORT
**Invoices Report** ({$from} to {$to})

- **Total Invoices:** {$total}
- **Fully Paid:** {$paid}
- **Total Invoiced Amount:** ₹{$totalAmount}
- **Total Collected:** ₹{$totalPaid}
- **Outstanding Balance:** ₹{$totalBalance}
- **Period:** {$from} to {$to}
REPORT;
    }

    private function customerSummary(?int $plantId, string $from, string $to, string $customer): string
    {
        if (empty($customer)) {
            return "Please provide a `customer_name` to generate a customer summary report.";
        }

        $patron = \App\Models\Patron::where('name', 'LIKE', "%{$customer}%")->first();

        if (!$patron) {
            return "No customer found matching '{$customer}'.";
        }

        $invoiceCount  = Invoice::where('patron_id', $patron->id)->whereBetween('invoice_date', [$from, $to])->count();
        $totalBilled   = Invoice::where('patron_id', $patron->id)->whereBetween('invoice_date', [$from, $to])->sum('grand_total');
        $totalPaid     = Invoice::where('patron_id', $patron->id)->whereBetween('invoice_date', [$from, $to])->sum('amount_paid');
        $balance       = $totalBilled - $totalPaid;

        return <<<REPORT
**Customer Summary: {$patron->name}** ({$from} to {$to})

- **Customer Code:** {$patron->code}
- **Invoices Raised:** {$invoiceCount}
- **Total Billed:** ₹{$totalBilled}
- **Amount Paid:** ₹{$totalPaid}
- **Outstanding Balance:** ₹{$balance}
REPORT;
    }
}
