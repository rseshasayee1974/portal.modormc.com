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
        @page { size: A5 portrait; margin: 9mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #223047; line-height: 1.3; margin: 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { vertical-align: top; word-wrap: break-word; }
        .brand { border-bottom: 2px solid #1d3557; padding-bottom: 8px; }
        .brand-name { font-size: 15px; font-weight: bold; color: #142a45; margin-bottom: 3px; }
        .brand-detail { font-size: 8.5px; color: #66758a; line-height: 1.35; }
        .document-title { font-size: 16px; font-weight: bold; color: #142a45; margin: 10px 0 7px; }
        .document-title span { font-size: 8px; font-weight: normal; color: #6c7c91; }
        .document-meta { margin-bottom: 9px; }
        .document-meta td { width: 50%; }
        .eyebrow { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.7px; color: #718096; margin-bottom: 3px; }
        .meta-value { font-size: 10px; font-weight: bold; color: #1d3557; }
        .right { text-align: right !important; }
        .amount-panel { margin-bottom: 10px; padding: 10px 12px; background: #eef3f9; border-left: 4px solid #1d3557; page-break-inside: avoid; }
        .amount-label { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #516881; }
        .amount-value { font-size: 24px; line-height: 1.2; font-weight: bold; color: #142a45; }
        .status { display: inline-block; padding: 3px 8px; border: 1px solid #bfccd9; border-radius: 3px; color: #435a73; background: #fff; font-size: 8px; font-weight: bold; }
        .party { border: 1px solid #dce4ed; padding: 9px 12px; margin-bottom: 9px; page-break-inside: avoid; }
        .party-name { font-size: 13px; font-weight: bold; color: #233c59; }
        .details { margin-top: 7px; padding-top: 7px; border-top: 1px solid #e5ebf1; }
        .details td { width: 50%; }
        .details-value { font-size: 9px; }
        .section-heading { font-size: 9px; font-weight: bold; color: #1d3557; margin: 10px 0 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .items thead { display: table-header-group; }
        .items th { background: #1d3557; color: #fff; padding: 5px 8px; text-align: left; font-size: 8px; font-weight: bold; }
        .items td { padding: 5px 8px; border-bottom: 1px solid #e4eaf0; font-size: 9px; }
        .items tr { page-break-inside: avoid; }
        .items tr:nth-child(even) td { background: #f7f9fc; }
        .notes { margin-top: 9px; padding: 8px 12px; border-left: 2px solid #c5d2e1; background: #fafbfd; word-wrap: break-word; font-size: 9px; }
        .notes-label { font-size: 8px; font-weight: bold; color: #6a7c91; text-transform: uppercase; margin-bottom: 4px; }
        .signatures { margin-top: 12px; page-break-inside: avoid; }
        .signatures td { width: 50%; }
        .signature-box { border: 1px solid #dce4ed; padding: 9px 12px; }
        .signature-space { height: 30px; }
        .signature-label { border-top: 1px solid #b9c7d6; padding-top: 6px; font-size: 9px; color: #425975; }
        .footer { margin-top: 10px; page-break-inside: avoid; }
        .footer td { width: 50%; font-size: 8px; color: #708096; }
        .prepared-name { color: #314b66; font-weight: bold; font-size: 9px; }
        .authorized-line { width: 125px; border-top: 1px solid #b9c7d6; padding-top: 5px; margin-left: auto; color: #425975; }
    </style>
</head>
<body>
    <div class="brand">
        <div class="brand-name">{{ $plant?->entity?->legal_name ?: $plant?->name ?: config('app.name') }}</div>
        @if($plant?->entity?->legal_name)<div class="brand-detail">{{ $plant->name }}</div>@endif
        @if($addressText)<div class="brand-detail">{{ $addressText }}</div>@endif
        @if($plant?->gstin)<div class="brand-detail">GSTIN {{ $plant->gstin }}</div>@endif
    </div>

    <div class="document-title">{{ $title }}</div>
    <table class="document-meta">
        <tr>
            <td><div class="eyebrow">Voucher Number</div><div class="meta-value">{{ $payment->reference ?: '#'.$payment->id }}</div></td>
            <td class="right"><div class="eyebrow">Transaction Date</div><div class="meta-value">{{ $payment->transaction_date?->format('d M Y') ?: '-' }}</div></td>
        </tr>
    </table>

    <div class="amount-panel">
        <table>
            <tr>
                <td style="width: 35%; padding-top: 4px;">
                    <div class="amount-label">{{ $receipt ? 'Amount Received' : 'Amount Paid' }}</div>
                    <div style="margin-top: 4px;"><span class="status">{{ ucfirst((string)$payment->status) }}</span></div>
                </td>
                <td class="right" style="width: 65%;"><div class="amount-value">₹ {{ number_format((float)$payment->amount, 2) }}</div></td>
            </tr>
        </table>
    </div>

    <div class="party">
        <div class="eyebrow">{{ $receipt ? 'Received From' : 'Paid To' }}</div>
        <div class="party-name">{{ $payment->patron?->legal_name ?: 'General Ledger' }}</div>
        <div class="details">
            <table><tr>
                <td><div class="eyebrow">Ledger Account</div><div class="details-value">{{ $payment->ledger?->title ?: '-' }}</div></td>
                <td style="padding-left: 12px;"><div class="eyebrow">Payment Mode</div><div class="details-value">{{ $payment->transaction_mode ?: '-' }}</div></td>
            </tr></table>
        </div>
    </div>

    @if($payment->allocations->isNotEmpty())
        <div class="section-heading">Invoice / Bill Allocations</div>
        <table class="items">
            <thead><tr><th style="width: 10%">S/No</th><th>Document Number</th><th class="right" style="width: 32%">Allocated Amount</th></tr></thead>
            <tbody>
                @foreach($payment->allocations as $allocation)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $allocation->invoice?->full_number ?: '#'.$allocation->invoice_id }}</td><td class="right">₹ {{ number_format((float)$allocation->amount, 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($payment->description)
        <div class="notes"><div class="notes-label">Description / Remarks</div>{!! nl2br(e($payment->description)) !!}</div>
    @endif

    <table class="signatures"><tr>
        <td style="padding-right: 6px;"><div class="signature-box"><div class="signature-space"></div><div class="signature-label">Paid By Signature</div></div></td>
        <td style="padding-left: 6px;"><div class="signature-box"><div class="signature-space"></div><div class="signature-label">Receiver Signature</div></div></td>
    </tr></table>
    <table class="footer"><tr>
        <td>Prepared By<br><span class="prepared-name">{{ $payment->creator?->username ?: '-' }}</span></td>
        <td class="right" style="padding-top: 11px;"><div class="authorized-line">Authorized Signatory</div></td>
    </tr></table>
</body>
</html>
