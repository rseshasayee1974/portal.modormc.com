<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;
use App\Services\Audit\AuditLogger;

class LoginController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    #[OA\Post(
        path: "/auth/login",
        summary: "User Login",
        tags: ["Authentication"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "email", type: "string", example: "demo@modomines.com"),
                new OA\Property(property: "mobile", type: "string", example: "9876543210"),
                new OA\Property(property: "password", type: "string", example: "password"),
                new OA\Property(property: "otp", type: "string", example: "123456")
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Login Success")]
    #[OA\Response(response: 401, description: "Unauthorized")]
    public function login(Request $request): JsonResponse
    {
        if ($request->filled('email')) {
            return $this->loginWithEmailPassword($request);
        }

        if ($request->filled('mobile')) {
            return $request->filled('otp')
                ? $this->verifyMobileOtpAndLogin($request)
                : $this->sendMobileOtp($request);
        }

        return response()->json([
            'status' => false,
            'message' => 'Validation Error',
            'errors' => [
                'email' => ['Email is required when mobile is not provided.'],
                'mobile' => ['Mobile is required when email is not provided.'],
            ],
        ], 422);
    }

    private function loginWithEmailPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::query()
            ->where('email', $request->string('email')->trim()->lower()->value())
            ->first();

        if (!$user || !Hash::check($request->string('password')->value(), $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'Your account is inactive. Please contact support.',
            ], 403);
        }

        return $this->issueLoginResponse($request, $user, 'email-login');
    }

    private function sendMobileOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::query()
            ->where('mobile', $this->normalizeMobile($request->string('mobile')->value()))
            ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Mobile number not found',
            ], 404);
        }

        if (!$user->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'Your account is inactive. Please contact support.',
            ], 403);
        }

        $otp = (string) random_int(100000, 999999);
        Cache::put($this->mobileOtpCacheKey($user->mobile), Hash::make($otp), now()->addMinutes(5));

        if (app()->environment('local')) {
            Log::info("[MOBILE OTP] User {$user->mobile} code: {$otp} (expires in 5 minutes)");
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP sent successfully',
            'login_type' => 'mobile_otp',
            'otp_required' => true,
            'expires_in' => 300,
            'mobile' => $user->mobile,
            'debug_otp' => app()->environment('local') ? $otp : null,
        ]);
    }

    private function verifyMobileOtpAndLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string|max:20',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mobile = $this->normalizeMobile($request->string('mobile')->value());
        $user = User::query()->where('mobile', $mobile)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Mobile number not found',
            ], 404);
        }

        if (!$user->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'Your account is inactive. Please contact support.',
            ], 403);
        }

        $cachedOtp = Cache::get($this->mobileOtpCacheKey($user->mobile));

        if (!$cachedOtp || !Hash::check($request->string('otp')->value(), $cachedOtp)) {
            return response()->json([
                'status' => false,
                'message' => 'The OTP is invalid or expired.',
            ], 422);
        }

        Cache::forget($this->mobileOtpCacheKey($user->mobile));

        return $this->issueLoginResponse($request, $user, 'mobile-login');
    }

    private function issueLoginResponse(Request $request, User $user, string $tokenName): JsonResponse
    {
        $user->forceFill([
            'last_login' => now(),
            'login_status' => true,
            'ip_address' => $request->ip(),
        ])->saveQuietly();

        $token = $user->createToken($tokenName)->plainTextToken;

        $this->auditLogger->logAuthEvent('LOGIN', $user, [
            'channel' => 'api',
            'token_name' => $tokenName,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Login Success',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'default_entity_id' => $user->default_entity_id,
                'default_plant_id' => $user->default_plant_id,
                'profile_photo_url' => $user->profile_photo_url,
            ],
        ]);
    }

    private function normalizeMobile(string $mobile): string
    {
        return preg_replace('/\s+/', '', trim($mobile)) ?? trim($mobile);
    }

    private function mobileOtpCacheKey(?string $mobile): string
    {
        return 'mobile_otp:'.$this->normalizeMobile((string) $mobile);
    }

    #[OA\Post(path: "/auth/logout", summary: "User Logout", tags: ["Authentication"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Logged out successfully")]
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $user?->currentAccessToken()?->delete();
        $user?->forceFill([
            'login_status' => false,
        ])->saveQuietly();

        if ($user) {
            $this->auditLogger->logAuthEvent('LOGOUT', $user, [
                'channel' => 'api',
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}