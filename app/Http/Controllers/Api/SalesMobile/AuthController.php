<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:00:44 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mobile\MobileAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Hash, RateLimiter, DB};
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', strtolower($data['email']))->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 401, 'Invalid email or password.');
        abort_unless($user->is_active && (!$user->lockout_until || $user->lockout_until->isPast()), 403, 'Account inactive or locked.');
        abort_unless($user->is_otp_enabled, 403, 'Mobile app access has not been granted or has been removed. Contact your administrator.');
        return $this->issue($user);
    }

    public function sendOtp(Request $request)
    {
        $data = $request->validate(['mobile' => ['required', 'string', 'regex:/^\+?[0-9][0-9 ()-]{8,23}$/']]);
        $mobile = $this->normalizeMobile($data['mobile']);
        $numberKey = 'sales-mobile:number:' . hash('sha256', $mobile);
        abort_if(RateLimiter::tooManyAttempts($numberKey, 3), 429, 'Too many OTP requests. Try again in a minute.');
        RateLimiter::hit($numberKey, 60);

        $matches = User::whereNotNull('mobile')->get(['id', 'mobile'])
            ->filter(fn(User $user) => $this->normalizeMobile($user->mobile) === $mobile);
        abort_if($matches->count() > 1, 422, 'Multiple users have this mobile number. Contact your administrator.');
        $user = User::whereIn('id', $matches->pluck('id'))->where('is_active', 1)->where('is_otp_enabled', 1)->first();
        abort_unless($user, 403, 'Mobile app access has not been granted or the account is inactive. Contact your administrator.');
        abort_unless(!$user->lockout_until || $user->lockout_until->isPast(), 403, 'Account inactive or locked.');

        $otp = (string) random_int(100000, 999999);
        $result = $this->sendWhatsAppTemplate(
            (string) '76484572302',
            (string) 'OVEfLmX6NtRvMlIsYauJ5KrZq',
            substr($mobile, 1),
            (string) 'sales_executive_login',
            $otp
        );
        if (!$this->isWhatsAppAccepted($result)) {
            $errorCode = $result['error_code'] ?? 'WHATSAPP_SEND_FAILED';
            $message = match ($errorCode) {
                'WHATSAPP_TLS_ERROR' => 'The WhatsApp provider HTTPS certificate does not validate.',
                'WHATSAPP_CONNECTION_ERROR' => 'Unable to connect to the WhatsApp provider. Try again shortly.',
                'WHATSAPP_CONFIG_ERROR' => 'WhatsApp OTP endpoint, credentials or template are not configured.',
                default => 'Unable to submit WhatsApp verification code. Contact your administrator.',
            };
            return response()->json(['success' => false, 'message' => $message, 'error_code' => $errorCode, 'code' => 503], 503);
        }

        $challenge = Str::random(64);
        Cache::put('sales-mobile:' . $challenge, [
            'user_id' => $user->id,
            'mobile' => $mobile,
            'access_revision' => (int) $user->mobile_access_revision,
            'otp' => Hash::make($otp),
        ], now()->addMinutes(5));
        return response()->json(['success' => true, 'data' => [
            'otp_required' => true,
            'channel' => 'whatsapp',
            'challenge_id' => $challenge,
            'expires_in' => 300,
        ]], 202);
    }

    private function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile);
        if (strlen($digits) === 10) $digits = (string)config('services.mobile_otp.country_code', '91') . $digits;
        return '+' . $digits;
    }

    protected function sendWhatsAppTemplate(string $licenseNumber, string $apiKey, string $contact, string $template, string $paramString): array
    {
        $url = (string) config('services.tendigit.endpoint', 'https://login.tendigit.in/api/sendtemplate.php');
        if (!$licenseNumber || !$apiKey || !$template || parse_url($url, PHP_URL_SCHEME) !== 'https' || !parse_url($url, PHP_URL_HOST)) {
            return ['error' => true, 'error_code' => 'WHATSAPP_CONFIG_ERROR'];
        }
        $params = [
            'LicenseNumber' => $licenseNumber,
            'APIKey' => $apiKey,
            'Contact' => $contact,
            'Template' => $template,
            'Param' => $paramString
        ];
        $curl = curl_init($url . '?' . http_build_query($params));
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30]);
        $response = curl_exec($curl);
        $errno = curl_errno($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($errno) {
            return ['error' => true, 'error_code' => in_array($errno, [51, 60, 77, 90, 91], true)
                ? 'WHATSAPP_TLS_ERROR' : 'WHATSAPP_CONNECTION_ERROR'];
        }
        return ['error' => false, 'http_status' => $httpStatus, 'response' => (string) $response];
    }

    private function isWhatsAppAccepted(array $result): bool
    {
        $httpStatus = $result['http_status'] ?? 0;
        $response = (string) ($result['response'] ?? '');
        if (($result['error'] ?? true) || $httpStatus < 200 || $httpStatus >= 300 || trim($response) === '') return false;

        $body = json_decode($response, true);
        $body = is_array($body) ? $body : [];
        $status = $body['status'] ?? $body['success'] ?? null;
        $error = $body['error'] ?? null;
        $code = $body['code'] ?? $body['Code'] ?? null;
        if ($status === false || in_array(strtolower((string) $status), ['error', 'failed', 'failure', 'false'], true)) return false;
        if (!in_array($error, [null, false, 0, '', 'false', '0'], true)) return false;
        if (is_numeric($code) && ((int) $code >= 400 || (int) $code < 0)) return false;

        $message = $body['message'] ?? $body['Message'] ?? $body['response'] ?? $response;
        return !is_string($message) || !preg_match('/\b(error|failed|failure|invalid|insufficient|unauthorized|unauthorised)\b|<html|<!doctype/i', $message);
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['challenge_id' => 'required|string|size:64', 'otp' => 'required|digits:6']);
        $key = 'sales-mobile:' . $data['challenge_id'];
        $lock = Cache::lock($key . ':verification', 10);
        abort_unless($lock->get(), 409, 'Verification already in progress. Try again shortly.');
        try {
            $attemptKey = $key . ':attempts';
            abort_if(RateLimiter::tooManyAttempts($attemptKey, 5), 429, 'Too many verification attempts. Sign in again.');
            RateLimiter::hit($attemptKey, 300);
            $challenge = Cache::get($key);
            abort_unless($challenge && Hash::check($data['otp'], $challenge['otp']), 422, 'Code invalid or expired.');
            abort_unless(Cache::pull($key), 422, 'Challenge already used.');
            $user = User::findOrFail($challenge['user_id']);
            abort_unless(isset($challenge['mobile']) && $this->normalizeMobile((string) $user->mobile) === $challenge['mobile'], 422, 'Mobile number changed. Request a new code.');
            abort_unless($user->is_active && (!$user->lockout_until || $user->lockout_until->isPast()), 403, 'Account inactive or locked.');
            abort_unless($user->is_otp_enabled && (int)$user->mobile_access_revision === ($challenge['access_revision'] ?? -1), 403, 'Mobile app access changed. Request a new code.');
            RateLimiter::clear($attemptKey);
            return $this->issue($user);
        } finally {
            $lock->release();
        }
    }

    private function issue(User $user)
    {
        $revision = (int)$user->mobile_access_revision;
        return DB::transaction(function () use ($user, $revision) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->is_active && (!$user->lockout_until || $user->lockout_until->isPast()), 403, 'Account inactive or locked.');
            abort_unless($user->is_otp_enabled && (int)$user->mobile_access_revision === $revision, 403, 'Mobile app access changed. Sign in again.');
            $expires = now()->addDays(30);
            $token = $user->createToken('sales-mobile:app', ['sales-mobile'], $expires);
            $user->forceFill(['last_login' => now()])->save();
            return response()->json(['success' => true, 'data' => ['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $expires->toIso8601String(), 'user' => $this->profile($user), 'plants' => $this->plantList($user)]]);
        });
    }

    private function profile(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->username, 'email' => $user->email, 'personnel_id' => $user->personnel?->id, 'mobile' => $user->mobile];
    }

    private function plantList(User $user)
    {
        return MobileAccess::plants($user)->orderBy('name')->get(['id', 'name', 'entity_id']);
    }

    public function me(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->profile($request->user())]);
    }
    public function plants(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->plantList($request->user())]);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'This device has been signed out.']);
    }
}
