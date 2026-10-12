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
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string', 'device_name' => 'required|string|max:100']);
        $user = User::where('email', strtolower($data['email']))->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 401, 'Invalid email or password.');
        abort_unless($user->is_active && (!$user->lockout_until || $user->lockout_until->isPast()), 403, 'Account inactive or locked.');
        return $this->issue($user, $data['device_name']);
    }

    public function sendOtp(Request $request, \App\Services\Mobile\MobileOtpSender $sender)
    {
        $data = $request->validate(['mobile' => ['required', 'string', 'regex:/^\+?[0-9][0-9 ()-]{8,23}$/'], 'device_name' => 'required|string|max:100']);
        $mobile = $sender->normalize($data['mobile']);
        $digits = substr($mobile, 1);
        $candidates = [$digits];
        $country = (string) config('services.mobile_otp.country_code', '91');
        if (str_starts_with($digits, $country) && strlen(substr($digits, strlen($country))) === 10) $candidates[] = substr($digits, strlen($country));
        $numberKey = 'sales-mobile:number:'.hash('sha256', $mobile);
        abort_if(RateLimiter::tooManyAttempts($numberKey, 3), 429, 'Too many OTP requests. Try again in a minute.');
        RateLimiter::hit($numberKey, 60);
        $matches = User::whereIn(DB::raw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '')"), $candidates)->limit(2)->get();
        abort_unless($matches->count() === 1, 422, 'Mobile number is not uniquely registered. Contact your administrator.');
        $user = $matches->first();
        abort_unless($user->is_active && (!$user->lockout_until || $user->lockout_until->isPast()), 403, 'Account inactive or locked.');
        $challenge = Str::random(64);
        $otp = (string) random_int(100000, 999999);
        Cache::put('sales-mobile:'.$challenge, ['user_id' => $user->id, 'mobile' => $mobile, 'device_name' => $data['device_name'], 'otp' => Hash::make($otp)], now()->addMinutes(5));
        try { $sender->send($mobile, $otp, $user->email); }
        catch (\Throwable $e) { Cache::forget('sales-mobile:'.$challenge); abort(503, 'Unable to submit WhatsApp verification code. Contact your administrator.'); }
        return response()->json(['success' => true, 'data' => ['otp_required' => true, 'channel' => 'whatsapp', 'challenge_id' => $challenge, 'expires_in' => 300]], 202);
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['challenge_id' => 'required|string|size:64', 'otp' => 'required|digits:6']);
        $key = 'sales-mobile:'.$data['challenge_id'];
        $lock = Cache::lock($key.':verification', 10);
        abort_unless($lock->get(), 409, 'Verification already in progress. Try again shortly.');
        try {
            $attemptKey = $key.':attempts';
            abort_if(RateLimiter::tooManyAttempts($attemptKey, 5), 429, 'Too many verification attempts. Sign in again.');
            RateLimiter::hit($attemptKey, 300);
            $challenge = Cache::get($key);
            abort_unless($challenge && Hash::check($data['otp'], $challenge['otp']), 422, 'Code invalid or expired.');
            abort_unless(Cache::pull($key), 422, 'Challenge already used.');
            $user = User::findOrFail($challenge['user_id']);
            abort_unless(isset($challenge['mobile']) && app(\App\Services\Mobile\MobileOtpSender::class)->normalize((string) $user->mobile) === $challenge['mobile'], 422, 'Mobile number changed. Request a new code.');
            abort_unless($user->is_active && (!$user->lockout_until || $user->lockout_until->isPast()), 403, 'Account inactive or locked.');
            RateLimiter::clear($attemptKey);
            return $this->issue($user, $challenge['device_name']);
        } finally {
            $lock->release();
        }
    }

    private function issue(User $user, string $device)
    {
        $expires = now()->addDays(30);
        $token = $user->createToken('sales-mobile:'.$device, ['sales-mobile'], $expires);
        $user->forceFill(['last_login' => now()])->save();
        return response()->json(['success' => true, 'data' => ['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $expires->toIso8601String(), 'user' => $this->profile($user), 'plants' => $this->plantList($user)]]);
    }

    private function profile(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->username, 'email' => $user->email, 'personnel_id' => $user->personnel?->id];
    }

    private function plantList(User $user)
    {
        return MobileAccess::plants($user)->orderBy('name')->get(['id', 'name', 'entity_id']);
    }

    public function me(Request $request) { return response()->json(['success' => true, 'data' => $this->profile($request->user())]); }
    public function plants(Request $request) { return response()->json(['success' => true, 'data' => $this->plantList($request->user())]); }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'This device has been signed out.']);
    }
}
