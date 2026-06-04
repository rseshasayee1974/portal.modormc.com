<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\DashboardService;
use OpenApi\Attributes as OA;
use App\Models\User;
use App\Models\Plant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MobileApiController extends Controller
{
    protected $dashboardService;

    /**
     * Inject DashboardService to provide dashboard statistics.
     */
    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    private function getFilters(Request $request)
    {
        return $request->only(['from_date', 'to_date', 'plant_id', 'type']);
    }

    /**
     * Get mobile API status.
     */
    #[OA\Get(
        path: "/api/mobile/status",
        summary: "Get Mobile API status",
        tags: ["Mobile API"]
    )]
    #[OA\Response(response: 200, description: "Success status response")]
    public function status(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Mobile API is active and running.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Mobile login endpoint.
     */
    #[OA\Post(
        path: "/api/mobile/login",
        summary: "Authenticate mobile app user",
        tags: ["Mobile API"]
    )]
    #[OA\Response(response: 200, description: "Authentication response")]
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

        if ($user->is_otp_enabled) {
            return response()->json([
                'message' => 'Two-factor authentication required.',
                '2fa_required' => true,
                'email' => $user->email,
            ], 200); // Or 403/401 depending on frontend preference, but 200 with status is common for SPA
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
                'plants' => $this->getPlantsForUser($user),
            ],
        ]);
    }

    /**
     * Send OTP.
     */
    #[OA\Post(path: "/api/mobile/send-otp", summary: "Send OTP to email", tags: ["Mobile API"])]
    #[OA\Response(response: 200, description: "OTP sent")]
    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:mm_users,email'],
        ]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();
        $otp = (string) random_int(100000, 999999);

        Cache::put($this->otpCacheKey($user->email), Hash::make($otp), now()->addMinutes(5));

        if (app()->environment('local')) {
            Log::info("[MOBILE EMAIL OTP] User {$user->email} code: {$otp} (expires in 5 minutes)");
        }

        return response()->json([
            'success' => true,
            'message' => 'A 6-digit verification code has been sent.',
            'expires_in' => 300,
        ]);
    }

    /**
     * Verify OTP.
     */
    #[OA\Post(path: "/api/mobile/verify-otp", summary: "Verify email OTP", tags: ["Mobile API"])]
    #[OA\Response(response: 200, description: "OTP verified")]
    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:mm_users,email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();
        $cachedOtp = Cache::get($this->otpCacheKey($user->email));

        if (!$cachedOtp || !Hash::check($data['otp'], $cachedOtp)) {
            return response()->json([
                'success' => false,
                'message' => 'The verification code is invalid or expired.'
            ], 422);
        }

        Cache::forget($this->otpCacheKey($user->email));

        if (!$user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user);
        $token = $user->createToken('email-otp-verification')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'plants' => $this->getPlantsForUser($user),
            ],
        ]);
    }

    /**
     * Get authenticated user details.
     */
    #[OA\Get(
        path: "/api/mobile/user",
        summary: "Get authenticated mobile user details",
        tags: ["Mobile API"],
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(response: 200, description: "User details response")]
    public function user(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Get mobile dashboard statistics.
     */
    #[OA\Get(
        path: "/api/mobile/dashboard",
        summary: "Get mobile app dashboard statistics",
        tags: ["Mobile API"],
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(response: 200, description: "Dashboard statistics response")]
    public function dashboard(Request $request)
    {
        $filters = $this->getFilters($request);
        
        $salesSummary = $this->dashboardService->getSalesSummary($filters);
        $stockDetails = $this->dashboardService->getStockDetails($filters);
        
        return response()->json([
            'success' => true,
            'data' => [
                'sales_summary' => $salesSummary,
                'stock_details' => $stockDetails,
            ]
        ]);
    }

    /**
     * Get sales summary.
     */
    #[OA\Get(path: "/api/mobile/sales-summary", summary: "Get sales summary", tags: ["Mobile API"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Success")]
    public function salesSummary(Request $request)
    {
        $data = $this->dashboardService->getSalesSummary($this->getFilters($request));
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Get sales details.
     */
    #[OA\Get(path: "/api/mobile/sales-details", summary: "Get sales details", tags: ["Mobile API"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Success")]
    public function salesDetails(Request $request)
    {
        $data = $this->dashboardService->getSalesDetails($this->getFilters($request));
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Get customer details.
     */
    #[OA\Get(path: "/api/mobile/customer-details", summary: "Get customer details", tags: ["Mobile API"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Success")]
    public function customerDetails(Request $request)
    {
        $data = $this->dashboardService->getCustomerDetails($this->getFilters($request));
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Get top mix designs.
     */
    #[OA\Get(path: "/api/mobile/top-mix-designs", summary: "Get top mix designs", tags: ["Mobile API"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Success")]
    public function topMixDesigns(Request $request)
    {
        $data = $this->dashboardService->getTopMixDesignsFromBatches($this->getFilters($request));
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Get dispatch details.
     */
    #[OA\Get(path: "/api/mobile/dispatch-details", summary: "Get dispatch details", tags: ["Mobile API"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Success")]
    public function dispatchDetails(Request $request)
    {
        $data = $this->dashboardService->getDispatchDetailsByTruck($this->getFilters($request));
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Get dispatch batching summary.
     */
    #[OA\Get(path: "/api/mobile/dispatch-batching-summary", summary: "Get dispatch batching summary", tags: ["Mobile API"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Success")]
    public function dispatchBatchingSummary(Request $request)
    {
        $data = $this->dashboardService->getDispatchBatchingSummary($this->getFilters($request));
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Get stock details.
     */
    #[OA\Get(path: "/api/mobile/stock-details", summary: "Get stock details", tags: ["Mobile API"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Success")]
    public function stockDetails(Request $request)
    {
        $data = $this->dashboardService->getStockDetails($this->getFilters($request));
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function getPlantsForUser(User $user)
    {
        if ($user->isSystemAdmin()) {
            return Plant::select('id', 'name as plant_name')->get();
        }
        
        $authorizedPlantIds = $user->entityUsers()
            ->whereNotNull('plant_id')
            ->pluck('plant_id')
            ->unique()
            ->toArray();
            
        return Plant::whereIn('id', $authorizedPlantIds)->select('id', 'name as plant_name')->get();
    }

    private function otpCacheKey(string $email): string
    {
        return 'email_otp:'.mb_strtolower($email);
    }
}
