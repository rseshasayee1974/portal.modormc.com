{{--
Author: ragul-onemodo
Created: 2026-11-05 15:15:46 Asia/Calcutta (UTC+05:30)
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales order created</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1e293b; line-height: 1.5;">
    <h1 style="font-size: 22px;">Sales order created</h1>
    <p>A new sales order has been recorded in ModoRMC.</p>
    <dl>
        <dt><strong>Sales order</strong></dt><dd>{{ $reference }}</dd>
        <dt><strong>Plant</strong></dt><dd>{{ $plant ?? '—' }}</dd>
        <dt><strong>Customer</strong></dt><dd>{{ $customer ?? '—' }}</dd>
        <dt><strong>Site</strong></dt><dd>{{ $site ?? '—' }}</dd>
        <dt><strong>Mix design</strong></dt><dd>{{ $mix ?? '—' }}</dd>
        <dt><strong>Ordered quantity</strong></dt><dd>{{ $quantity ?? '—' }}</dd>
    </dl>
    <p><a href="{{ $salesOrdersUrl }}">Open Sales Orders</a></p>
</body>
</html>
