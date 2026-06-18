<?php

namespace App\Ai\Tools;

use App\Models\Invoice;
use Laravel\Ai\Contracts\Tool;
use Stringable;

/**
 * Tool: Search Invoices
 *
 * Allows the internal assistant to search invoices by customer, number, or status.
 */
class SearchInvoices implements Tool
{
    public function name(): Stringable|string
    {
        return 'search_invoices';
    }

    public function description(): Stringable|string
    {
        return 'Search customer invoices by invoice number, customer name, payment status, or date range. Returns invoice details including amounts and payment status.';
    }

    public function parameters(): array
    {
        return [
            'query' => [
                'type'        => 'string',
                'description' => 'Invoice number or customer name to search for',
                'required'    => false,
            ],
            'payment_status' => [
                'type'        => 'string',
                'description' => 'Filter by payment status: unpaid, partial, paid',
                'required'    => false,
            ],
            'date_from' => [
                'type'        => 'string',
                'description' => 'Invoice date from (YYYY-MM-DD)',
                'required'    => false,
            ],
            'date_to' => [
                'type'        => 'string',
                'description' => 'Invoice date to (YYYY-MM-DD)',
                'required'    => false,
            ],
            'limit' => [
                'type'        => 'integer',
                'description' => 'Maximum results (default: 10)',
                'required'    => false,
            ],
        ];
    }

    public function handle(
        string $query = '',
        string $payment_status = '',
        string $date_from = '',
        string $date_to = '',
        int    $limit = 10
    ): string {
        $plantId = session('active_plant_id');

        $invoiceQuery = Invoice::query()
            ->with(['patron:id,name', 'invoiceStatus:id,name'])
            ->select([
                'id', 'invoice_no', 'invoice_date', 'grand_total',
                'balance_due', 'payment_status', 'patron_id', 'plant_id',
            ])
            ->orderByDesc('invoice_date')
            ->take(min($limit, 20));

        if ($plantId) {
            $invoiceQuery->where('plant_id', $plantId);
        }

        if (!empty($payment_status)) {
            $invoiceQuery->where('payment_status', $payment_status);
        }

        if (!empty($date_from)) {
            $invoiceQuery->whereDate('invoice_date', '>=', $date_from);
        }

        if (!empty($date_to)) {
            $invoiceQuery->whereDate('invoice_date', '<=', $date_to);
        }

        if (!empty($query)) {
            $invoiceQuery->where(function ($q) use ($query) {
                $q->where('invoice_no', 'LIKE', "%{$query}%")
                  ->orWhereHas('patron', fn ($p) => $p->where('name', 'LIKE', "%{$query}%"));
            });
        }

        $invoices = $invoiceQuery->get();

        if ($invoices->isEmpty()) {
            return "No invoices found" . (!empty($query) ? " matching '{$query}'" : '') . ".";
        }

        $result = "Found {$invoices->count()} invoice(s):\n\n";

        foreach ($invoices as $invoice) {
            $customer    = $invoice->patron?->name ?? 'Unknown';
            $total       = number_format($invoice->grand_total ?? 0, 2);
            $balance     = number_format($invoice->balance_due ?? 0, 2);
            $status      = $invoice->payment_status ?? 'Unknown';
            $date        = $invoice->invoice_date?->format('d M Y') ?? 'N/A';

            $result .= "• **Invoice #{$invoice->invoice_no}** — {$date}\n";
            $result .= "  Customer: {$customer}\n";
            $result .= "  Total: ₹{$total} | Balance Due: ₹{$balance}\n";
            $result .= "  Payment Status: {$status}\n\n";
        }

        return $result;
    }
}
