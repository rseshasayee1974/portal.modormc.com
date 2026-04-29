<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthApiController extends Controller
{
    private function canManageBillingApiKey(User $user): bool
    {
        $roles = $user->getRoleNames()->map(fn ($r) => mb_strtolower((string) $r))->all();
        return in_array('saas owner', $roles, true) || in_array('platform admin', $roles, true);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:mm_users,email'],
            'password' => ['required', 'string', 'min:8'],
            'plan' => ['nullable', 'in:free,paid'],
        ]);

        $user = User::query()->create([
            'username' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'api_key' => Str::random(60),
            'plan' => $data['plan'] ?? 'free',
            'is_active' => 1,
        ]);

        return response()->json([
            'message' => 'Registered successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'plan' => $user->plan,
                'api_key' => $user->api_key,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Your account is inactive. Please contact support.'], 403);
        }

        if (!$user->api_key) {
            $user->forceFill(['api_key' => Str::random(60)])->save();
        }

        Auth::login($user, (bool) ($data['remember'] ?? false));
        $token = $user->createToken('web-login')->plainTextToken;
        $user->forceFill(['last_login' => now()])->save();

        return response()->json([
            'message' => 'Logged in successfully',
            'token' => $token,
            'token_type' => 'Bearer',
            'redirect_to' => '/dashboard',
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'plan' => $user->plan,
                'api_key' => $user->api_key,
                'email_verified_at' => $user->email_verified_at,
            ],
        ]);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:mm_users,email'],
        ]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();
        $otp = (string) random_int(100000, 999999);

        Cache::put($this->otpCacheKey($user->email), Hash::make($otp), now()->addMinutes(5));

        // Replace this with a notification/mail class when the OTP delivery provider is ready.
        if (app()->environment('local')) {
            Log::info("[EMAIL OTP] User {$user->email} code: {$otp} (expires in 5 minutes)");
        }

        return response()->json([
            'message' => 'A 6-digit verification code has been sent.',
            'expires_in' => 300,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:mm_users,email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();
        $cachedOtp = Cache::get($this->otpCacheKey($user->email));

        if (!$cachedOtp || !Hash::check($data['otp'], $cachedOtp)) {
            return response()->json(['message' => 'The verification code is invalid or expired.'], 422);
        }

        Cache::forget($this->otpCacheKey($user->email));

        if (!$user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user);
        $token = $user->createToken('email-otp-verification')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully.',
            'token' => $token,
            'token_type' => 'Bearer',
            'redirect_to' => '/dashboard',
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
            ],
        ]);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:mm_users,email'],
        ]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'This email address is already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'A fresh verification email has been sent.',
        ]);
    }

    public function regenerateApiKey(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->canManageBillingApiKey($user), 403, 'Only SaaS Owner or Platform Admin can regenerate API key.');
        $user->forceFill(['api_key' => Str::random(60)])->save();

        return response()->json([
            'message' => 'API key regenerated',
            'api_key' => $user->api_key,
        ]);
    }

    public function ensureApiKey(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->canManageBillingApiKey($user), 403, 'Only SaaS Owner or Platform Admin can generate API key.');

        if (!$user->api_key) {
            $user->forceFill(['api_key' => Str::random(60)])->save();
        }

        return response()->json([
            'api_key' => $user->api_key,
            'plan' => $user->plan,
        ]);
    }

    private function otpCacheKey(string $email): string
    {
        return 'email_otp:'.mb_strtolower($email);
    }
}
