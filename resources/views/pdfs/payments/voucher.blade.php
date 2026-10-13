{{--
Author: ragul-onemodo
Created: 2026-10-13 10:14:46 Asia/Calcutta (UTC+05:30)
--}}
@php
    $receipt = strtolower((string)$payment->transaction_type) === 'receipt';
    $title = $receipt ? 'Receipt Voucher' : 'Payment Voucher';
    $plant = $payment->plant;
    $address = $plant?->addresses->firstWhere('is_primary', true) ?? $plant?->addresses->first();
    $addressText = $address ? implode(', ', array_filter([$address->line_1, $address->line_2, $address->city, $address->zipcode])) : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} - {{ $payment->reference }}</title>
    <style>
        @page { size: A5 portrait; margin: 10mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #172033; line-height: 1.5; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .header { text-align: center; padding-bottom: 12px; border-bottom: 2px solid #253858; }
        .muted { color: #65738a; }
        .title { font-size: 13px; font-weight: bold; text-align: center; text-transform: uppercase; letter-spacing: 1px; margin: 14px 0; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        .meta td { padding: 5px 7px; vertical-align: top; word-wrap: break-word; }
        .label { width: 28%; color: #65738a; }
        .value { font-weight: bold; }
        .section { margin-top: 16px; font-size: 10px; font-weight: bold; }
        .items th { background: #eef2f7; padding: 7px; text-align: left; font-size: 8px; }
        .items td { padding: 7px; border-bottom: 1px solid #e2e8f0; word-wrap: break-word; }
        .items thead { display: table-header-group; }
        .items tr { page-break-inside: avoid; }
        .right { text-align: right !important; }
        .amount { padding: 12px; margin-top: 16px; border: 1px solid #cbd5e1; background: #f6f8fb; text-align: right; page-break-inside: avoid; }
        .amount strong { font-size: 18px; margin-left: 12px; }
        .notes { margin-top: 14px; word-wrap: break-word; }
        .signatures { margin-top: 48px; page-break-inside: avoid; }
        .signatures td { width: 25%; text-align: center; padding-top: 8px; border-top: 1px solid #cbd5e1; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $plant?->entity?->legal_name ?: $plant?->name ?: config('app.name') }}</h1>
        @if($plant?->entity?->legal_name)<div>{{ $plant->name }}</div>@endif
        @if($addressText)<div class="muted">{{ $addressText }}</div>@endif
        @if($plant?->gstin)<div class="muted">GSTIN: {{ $plant->gstin }}</div>@endif
    </div>
    <div class="title">{{ $title }}</div>
    <table class="meta">
        <tr><td class="label">Voucher No.</td><td class="value">{{ $payment->reference ?: '#'.$payment->id }}</td></tr>
        <tr><td class="label">Date</td><td class="value">{{ $payment->transaction_date?->format('d-m-Y') ?: '-' }}</td></tr>
        <tr><td class="label">{{ $receipt ? 'Received From' : 'Paid To' }}</td><td class="value">{{ $payment->patron?->legal_name ?: 'General Ledger' }}</td></tr>
        <tr><td class="label">Ledger Account</td><td>{{ $payment->ledger?->title ?: '-' }}</td></tr>
        <tr><td class="label">Mode</td><td>{{ $payment->transaction_mode ?: '-' }}</td></tr>
        <tr><td class="label">Status</td><td>{{ ucfirst((string)$payment->status) }}</td></tr>
    </table>
    @if($payment->allocations->isNotEmpty())
        <div class="section">Invoice / Bill Allocations</div>
        <table class="items">
            <thead><tr><th style="width: 12%">S/No</th><th>Invoice / Bill</th><th class="right" style="width: 28%">Amount</th></tr></thead>
            <tbody>
                @foreach($payment->allocations as $allocation)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $allocation->invoice?->full_number ?: '#'.$allocation->invoice_id }}</td><td class="right">₹ {{ number_format((float)$allocation->amount, 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <div class="amount">{{ $receipt ? 'Receipt Amount' : 'Payment Amount' }}<strong>₹ {{ number_format((float)$payment->amount, 2) }}</strong></div>
    @if($payment->description)<div class="notes"><b>Description</b><br>{!! nl2br(e($payment->description)) !!}</div>@endif
    <table class="signatures"><tr><td>Prepared By<br>{{ $payment->creator?->username ?: '-' }}</td><td>Paid By Signature</td><td>Receiver Signature</td><td>Authorized Signatory</td></tr></table>
</body>
</html>
