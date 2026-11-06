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
<body style="margin:0;padding:0;background-color:#f1f5f9;color:#1e293b;font-family:Arial,Helvetica,sans-serif;line-height:1.5;">
    <div style="display:none;font-size:1px;color:#f1f5f9;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">New sales order {{ $reference }} for {{ $customer ?: 'your customer' }} — {{ is_numeric($quantity) ? number_format((float) $quantity, 2) : '—' }} m³.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;">
        <tr><td align="center" style="padding:28px 12px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">
                <tr><td style="padding:0 8px 18px;font-size:22px;font-weight:bold;letter-spacing:-0.5px;color:#4338ca;">ModoRMC <span style="font-size:11px;font-weight:normal;letter-spacing:1px;color:#64748b;"> / ORDER NOTIFICATION</span></td></tr>
                <tr><td style="background-color:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr><td style="background-color:#312e81;padding:28px 26px;border-radius:15px 15px 0 0;">
                            <p style="margin:0 0 10px;color:#c7d2fe;font-size:11px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;">Sales order created</p>
                            <h1 style="margin:0;color:#ffffff;font-size:28px;line-height:1.3;font-weight:bold;overflow-wrap:anywhere;word-break:break-word;">{{ $reference }}</h1>
                            <p style="margin:12px 0 0;color:#e0e7ff;font-size:14px;">A new order has been recorded for {{ $plant ?: 'your plant' }}.</p>
                        </td></tr>
                        <tr><td style="padding:26px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef2ff;border:1px solid #e0e7ff;border-radius:10px;">
                                <tr><td style="padding:18px 20px;">
                                    <p style="margin:0;color:#4f46e5;font-size:11px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;">Ordered quantity</p>
                                    <p style="margin:6px 0 0;color:#312e81;font-size:30px;font-weight:bold;line-height:1.2;">{{ is_numeric($quantity) ? number_format((float) $quantity, 2) : '—' }} <span style="font-size:16px;font-weight:normal;">m³</span></p>
                                </td></tr>
                            </table>
                            <h2 style="margin:26px 0 10px;font-size:15px;font-weight:bold;color:#0f172a;">Order details</h2>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;table-layout:fixed;">
                                @foreach (['Customer' => $customer, 'Site location' => $site, 'Mix design' => $mix, 'Plant' => $plant] as $label => $value)
                                    <tr>
                                        <td width="34%" valign="top" style="width:34%;padding:13px 12px 13px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">{{ $label }}</td>
                                        <td valign="top" style="padding:13px 0;border-bottom:1px solid #e2e8f0;font-weight:bold;color:#1e293b;overflow-wrap:anywhere;word-break:break-word;">{{ $value ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </table>
                            <p style="margin:24px 0 16px;color:#64748b;font-size:13px;">Review this order and its production details in ModoRMC.</p>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                                <td bgcolor="#4f46e5" style="border-radius:8px;text-align:center;mso-padding-alt:13px 24px;">
                                    <a href="{{ $salesOrdersUrl }}" style="display:inline-block;padding:13px 24px;border:1px solid #4f46e5;border-radius:8px;color:#ffffff;font-size:14px;font-weight:bold;text-decoration:none;">Open Sales Orders &rarr;</a>
                                </td>
                            </tr></table>
                            <p style="margin:18px 0 0;font-size:11px;color:#64748b;">If the button does not work, <a href="{{ $salesOrdersUrl }}" style="color:#4338ca;text-decoration:underline;">open the Sales Orders page here</a>.</p>
                        </td></tr>
                        <tr><td style="padding:16px 26px;background-color:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 15px 15px;color:#64748b;font-size:12px;">Thank you,<br><strong style="color:#334155;">The ModoRMC Team</strong></td></tr>
                    </table>
                </td></tr>
                <tr><td align="center" style="padding:20px 14px 0;color:#64748b;font-size:11px;">You received this automated notification because your email is configured for Sales Order alerts at {{ $plant ?: 'this plant' }}.</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
