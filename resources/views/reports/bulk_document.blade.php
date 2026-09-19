<!doctype html>
<html><head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #172033; }
h1 { font-size: 18pt; margin: 0 0 5mm; }
h2 { font-size: 12pt; margin: 0 0 2mm; }
table { width: 100%; border-collapse: collapse; }
td, th { padding: 2mm; vertical-align: top; }
.header { border-bottom: 2px solid #334155; margin-bottom: 5mm; }
.details { margin-bottom: 5mm; }
.items td, .items th { border: 1px solid #cbd5e1; }
.items th { background: #edf2f7; font-size: 8pt; }
.items td { font-size: 8pt; }
.right { text-align: right; }
.muted { color: #64748b; font-size: 8pt; }
.totals { margin-top: 4mm; }
.totals td { padding: 1.5mm; }
.grand { font-weight: bold; background: #edf2f7; }
.notes { margin-top: 6mm; font-size: 8pt; }
.footer { margin-top: 12mm; border-top: 1px solid #cbd5e1; padding-top: 3mm; }
</style></head><body>
@php
    $bill = strtolower($invoice->invoice_type) === 'bill';
    $plant = $invoice->plant;
    $party = $invoice->partner;
    $money = fn($n) => number_format((float) $n, 2);
    $address = fn($a) => $a ? implode(', ', array_filter([$a->line_1, $a->line_2, $a->city, $a->zipcode])) : '';
    $discount = $invoice->global_discount_type === '%'
        ? (float)$invoice->subtotal * (float)$invoice->global_discount / 100
        : (float)$invoice->global_discount;
@endphp
<table class="header"><tr><td>
    <h2>{{ $plant?->entity?->legal_name ?? $plant?->name }}</h2>
    {{ $plant?->name }}<br>{{ $address($plant?->addresses->first()) }}<br>
    GSTIN: {{ $plant?->gstin ?? '—' }}
</td><td class="right"><h1>{{ $bill ? 'BILL' : ((float)$invoice->tax_amount > 0 ? 'TAX INVOICE' : 'INVOICE') }}</h1><span class="muted">{{ $invoice->invoice_label }} · COPY</span></td></tr></table>
<table class="details"><tr><td style="width:55%">
    <strong>{{ $bill ? 'Supplier / Vendor' : 'Bill to' }}</strong><br>
    {{ $party?->legal_name ?? '—' }}<br>{{ $address($party?->addresses->first()) }}<br>
    GSTIN: {{ $party?->gstin ?? '—' }}
</td><td><strong>Document:</strong> {{ $invoice->full_number }}<br>
    <strong>Date:</strong> {{ $invoice->invoice_date?->format('d-m-Y') }}<br>
    @if($invoice->due_date)<strong>Due date:</strong> {{ $invoice->due_date->format('d-m-Y') }}@endif
</td></tr></table>
<table class="items"><thead><tr><th>#</th><th>Description / HSN</th><th>Qty / Unit</th><th>Rate</th><th>Discount</th><th>Taxable</th><th>Tax</th><th>Total</th></tr></thead><tbody>
@foreach($invoice->items as $item)
<tr><td>{{ $loop->iteration }}</td><td>{{ $item->item_name }}<br><span class="muted">{{ $item->hsn_code }}</span></td><td class="right">{{ $money($item->quantity) }} {{ $item->uom?->unit_name }}</td><td class="right">{{ $money($item->price_unit) }}</td><td class="right">{{ $money($item->discount_amount) }}</td><td class="right">{{ $money($item->subtotal) }}</td><td class="right">{{ $money($item->line_tax_amount) }}</td><td class="right">{{ $money($item->line_total) }}</td></tr>
@endforeach
</tbody></table>
<table class="totals"><tr><td style="width:55%">
    @foreach($invoice->orderTaxes->groupBy('name') as $name => $taxes)
    {{ $name }}: {{ $money($taxes->sum('amount')) }}<br>
    @endforeach
</td><td><table>
<tr><td>Subtotal</td><td class="right">{{ $money($invoice->subtotal) }}</td></tr>
@if($discount)<tr><td>Discount</td><td class="right">-{{ $money(abs($discount)) }}</td></tr>@endif
<tr><td>Tax</td><td class="right">{{ $money($invoice->tax_amount) }}</td></tr>
@if($invoice->shipping_charges)<tr><td>Shipping</td><td class="right">{{ $money($invoice->shipping_charges) }}</td></tr>@endif
@if($invoice->adjustment)<tr><td>Adjustment</td><td class="right">{{ $money($invoice->adjustment) }}</td></tr>@endif
@if($invoice->round_off)<tr><td>Round off</td><td class="right">{{ $money($invoice->round_off) }}</td></tr>@endif
<tr class="grand"><td>Total (INR)</td><td class="right">{{ $money($invoice->total_amount) }}</td></tr>
</table></td></tr></table>
@if($invoice->notes)<div class="notes"><strong>Notes:</strong> {{ strip_tags($invoice->notes) }}</div>@endif
<div class="footer">{{ $bill ? 'Bill copy for accounts' : 'Authorised signatory' }}<span class="muted" style="float:right">{{ $invoice->full_number }}</span></div>
</body></html>
