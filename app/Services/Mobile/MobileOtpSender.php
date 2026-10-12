<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:34:35 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Mobile;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MobileOtpSender
{
    public function normalize(string $mobile): string
    {
        $digits = preg_replace('/[^0-9]/', '', $mobile);
        if (strlen($digits) === 10 && !str_starts_with(trim($mobile), '+')) $digits = config('services.mobile_otp.country_code', '91').$digits;
        if (!preg_match('/^[1-9][0-9]{10,14}$/', $digits)) throw ValidationException::withMessages(['mobile' => 'Enter a valid mobile number with country code.']);
        return '+'.$digits;
    }

    public function send(string $mobile, string $otp, string $email): void
    {
        $license = config('services.tendigit.license_number');
        $apiKey = config('services.tendigit.api_key');
        $template = config('services.mobile_otp.template');
        if (!$license || !$apiKey || !$template) throw new \RuntimeException('WhatsApp OTP provider is not configured.');
        // Reuse the approved ERP login template: email, code label, OTP.
        // Never log this request or its provider response: both can contain credentials or the OTP.
        $response = Http::connectTimeout(5)->timeout(15)->get('https://app.tendigit.in/api/sendtemplate.php', [
            'LicenseNumber' => $license, 'APIKey' => $apiKey, 'Contact' => substr($mobile, 1),
            'Template' => $template, 'Param' => implode(',', [str_replace(',', ';', $email), 'Your Code', $otp]),
        ]);
        $body = $response->json();
        $status = is_array($body) ? ($body['status'] ?? $body['success'] ?? null) : null;
        $error = is_array($body) ? ($body['error'] ?? null) : null;
        $code = is_array($body) ? ($body['code'] ?? $body['Code'] ?? null) : null;
        $rejected = !$response->successful() || trim($response->body()) === '' || $status === false
            || in_array(strtolower((string) $status), ['error', 'failed', 'failure', 'false'])
            || !in_array($error, [null, false, 0, '', 'false', '0'], true)
            || (is_numeric($code) && ((int) $code >= 400 || (int) $code < 0))
            || preg_match('/\b(error|failed|failure|invalid|insufficient|unauthorized|unauthorised)\b/i', strip_tags($response->body()))
            || preg_match('/<(html|!doctype)\b/i', $response->body());
        if ($rejected) throw new \RuntimeException('WhatsApp OTP submission was rejected.');
    }
}
