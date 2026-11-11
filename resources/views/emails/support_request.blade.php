{{--
Author: Cipher
Created: 2026-11-11 12:57:38 Asia/Calcutta (UTC+05:30)
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>MODORMC support request #{{ $supportRequest->id }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;color:#1e293b;font-family:Arial,Helvetica,sans-serif;line-height:1.5;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ $supportRequest->name }} submitted a {{ str_replace('_', ' ', $supportRequest->issue_type) }} for MODORMC.
    </div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="620" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:620px;">
                    <tr>
                        <td style="padding:0 8px 18px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td valign="middle" style="padding-right:12px;">
                                        <img src="{{ asset('assets/modormc_logo_v1.0.png') }}" width="46" height="46" alt="MODORMC" style="display:block;width:46px;height:46px;object-fit:contain;">
                                    </td>
                                    <td valign="middle">
                                        <div style="font-size:19px;font-weight:bold;letter-spacing:.5px;color:#312e81;">MODORMC</div>
                                        <div style="font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#64748b;">Support request</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="overflow:hidden;border:1px solid #e2e8f0;border-radius:16px;background-color:#ffffff;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:26px 28px;background-color:#312e81;">
                                        <p style="margin:0 0 8px;color:#c7d2fe;font-size:11px;font-weight:bold;letter-spacing:1.4px;text-transform:uppercase;">New mobile app request</p>
                                        <h1 style="margin:0;color:#ffffff;font-size:25px;line-height:1.3;overflow-wrap:anywhere;">{{ $supportRequest->subject }}</h1>
                                        <p style="margin:12px 0 0;color:#e0e7ff;font-size:13px;">Request #{{ $supportRequest->id }} · {{ $supportRequest->created_at?->format('M j, Y · g:i A') }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:26px 28px;">
                                        <span style="display:inline-block;padding:6px 11px;border:1px solid #e0e7ff;border-radius:999px;background-color:#eef2ff;color:#4338ca;font-size:11px;font-weight:bold;letter-spacing:.4px;text-transform:uppercase;">
                                            {{ ucwords(str_replace('_', ' ', $supportRequest->issue_type)) }}
                                        </span>

                                        <h2 style="margin:22px 0 8px;color:#0f172a;font-size:15px;">User and device</h2>
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="table-layout:fixed;border-collapse:collapse;font-size:13px;">
                                            @foreach ([
                                                'Name' => $supportRequest->name,
                                                'Email' => $supportRequest->email,
                                                'App version' => $supportRequest->app_version ?: 'Not provided',
                                                'Device / OS' => $supportRequest->device_details ?: 'Not provided',
                                            ] as $label => $value)
                                                <tr>
                                                    <td width="30%" valign="top" style="width:30%;padding:10px 12px 10px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">{{ $label }}</td>
                                                    <td valign="top" style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#1e293b;font-weight:bold;overflow-wrap:anywhere;word-break:break-word;">
                                                        @if ($label === 'Email')
                                                            <a href="mailto:{{ $value }}" style="color:#4338ca;text-decoration:underline;">{{ $value }}</a>
                                                        @else
                                                            {{ $value }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>

                                        <h2 style="margin:24px 0 8px;color:#0f172a;font-size:15px;">Issue or difficulty</h2>
                                        <div style="padding:16px;border:1px solid #e2e8f0;border-radius:10px;background-color:#f8fafc;color:#334155;font-size:14px;white-space:pre-wrap;overflow-wrap:anywhere;">{{ $supportRequest->message }}</div>

                                        @if ($supportRequest->steps_to_reproduce)
                                            <h2 style="margin:22px 0 8px;color:#0f172a;font-size:15px;">Steps to reproduce / additional details</h2>
                                            <div style="padding:16px;border:1px solid #e2e8f0;border-radius:10px;background-color:#f8fafc;color:#334155;font-size:14px;white-space:pre-wrap;overflow-wrap:anywhere;">{{ $supportRequest->steps_to_reproduce }}</div>
                                        @endif

                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;">
                                            <tr>
                                                <td bgcolor="#4f46e5" style="border-radius:8px;text-align:center;">
                                                    <a href="mailto:{{ $supportRequest->email }}?subject={{ rawurlencode('Re: '.$supportRequest->subject) }}" style="display:inline-block;padding:12px 20px;border:1px solid #4f46e5;border-radius:8px;color:#ffffff;font-size:13px;font-weight:bold;text-decoration:none;">Reply to user</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 28px;border-top:1px solid #e2e8f0;background-color:#f8fafc;color:#64748b;font-size:11px;">
                                        This message was submitted through the public MODORMC Support page. Request ID: {{ $supportRequest->id }}.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:18px 12px 0;color:#94a3b8;font-size:11px;">Onemodo Technologies · MODORMC</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
