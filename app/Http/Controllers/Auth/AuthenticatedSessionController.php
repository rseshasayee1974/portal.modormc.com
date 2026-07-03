<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Notifications\LoginOtpNotification;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'defaultLayout' => env('LOGIN_LAYOUT', 'centered'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request)
    {
        $request->ensureIsNotRateLimited();

        $user = User::where('email', $request->email)->first();

        if (!$user || !\Hash::check($request->password, $user->password)) {
            \Illuminate\Support\Facades\RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        \Illuminate\Support\Facades\RateLimiter::clear($request->throttleKey());

        // Check if Two-Factor Authentication is enabled and confirmed
        if (optional($user)->two_factor_secret &&
            !is_null(optional($user)->two_factor_confirmed_at)) {
            
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            event(new \Laravel\Fortify\Events\TwoFactorAuthenticationChallenged($user));

            return $request->wantsJson()
                ? response()->json(['two_factor' => true])
                : redirect()->route('two-factor.login');
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home', route('dashboard', absolute: false)));
    }

    /**
     * Send OTP for passwordless login.
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
        ]);

        $identifier = $request->identifier;
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);

        $user = User::where($isEmail ? 'email' : 'mobile', $identifier)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'identifier' => ['We could not find a user with that email address or mobile number.'],
            ]);
        }

        // Generate 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        // Store OTP in Database (otp_secret)
        $user->otp_secret = $otp;
        $user->save();

        $whatsappSent = false;
        $smsSent = false;

        // Send OTP via WhatsApp if mobile number exists
        if (!empty($user->mobile)) {
            try {
                $number = preg_replace('/\D/', '', $user->mobile);
                if (strlen($number) === 12 && str_starts_with($number, '91')) {
                    $number = substr($number, 2);
                }

                $licenseNumber = "76484572302";
                $apiKey = "PhaO2ElxqQ78WyRC61dN5zInt";
                $templateName = "modormcloginotp";
                $code = 'Your Code';
                $paramString = implode(',', [$user->email, $code, $otp]);

                $result = $this->sendWhatsAppTemplate($licenseNumber, $apiKey, '91' . $number, $templateName, $paramString);

                if ($result['error']) {
                    Log::error('Failed to send login OTP via WhatsApp: ' . $result['message']);
                } else {
                    Log::info("[LOGIN OTP WHATSAPP] Sent to 91{$number}. Response: " . $result['response']);
                    $whatsappSent = true;
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send login OTP via WhatsApp: ' . $e->getMessage());
            }
        }

        // Send OTP via Mobile SMS if mobile number exists
        if (!empty($user->mobile)) {
            try {
                $number = preg_replace('/\D/', '', $user->mobile);
                if (strlen($number) === 12 && str_starts_with($number, '91')) {
                    $number = substr($number, 2);
                }

                $url = "http://app.mydreamstechnology.in/vb/apikey.php";
                $apiKey = "eoGm9YOgFMqcP858";
                $senderId = "ONEMOD";
                $code = 'Your Code';
                $mobileNumber = "+91$number";
                $message = "Hello {$user->email},\n{$code} for modormc is {$otp},\nThank you kindly for your continued patronage with us. - ONEMODO";
                $encodedMessage = urlencode($message);
                $apiUrl = "{$url}?apikey={$apiKey}&senderid={$senderId}&number={$mobileNumber}&message={$encodedMessage}";

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                Log::info("[LOGIN OTP SMS] Sent to {$mobileNumber}. Response: " . $response . " (HTTP {$httpCode})");

                if ($response !== false && $httpCode === 200 && !empty($response)) {
                    $smsSent = true;
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send login OTP via SMS: ' . $e->getMessage());
            }
        }

        // Send OTP via email if the user has an email address registered
        if (!empty($user->email)) {
            try {
                $user->notify(new LoginOtpNotification($otp));
                Log::info("[LOGIN OTP EMAIL] Sent OTP to {$user->email}");
            } catch (\Throwable $e) {
                Log::error('Failed to send login OTP via Email: ' . $e->getMessage());
            }
        }

        // Log OTP in local/dev environment for easy retrieval
        Log::info("[LOGIN OTP] User identifier {$identifier} (email: {$user->email}, mobile: {$user->mobile}) — code: {$otp}");

        return response()->json(['message' => 'A login OTP has been sent.']);
    }

    /**
     * Authenticate user using OTP.
     */
    public function loginWithOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'otp' => 'required|string|digits:6',
        ]);

        $identifier = $request->identifier;
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);

        $user = User::where($isEmail ? 'email' : 'mobile', $identifier)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'identifier' => ['We could not find a user with that email address or mobile number.'],
            ]);
        }

        if (empty($user->otp_secret) || $user->otp_secret !== $request->otp) {
            throw ValidationException::withMessages([
                'otp' => ['The provided OTP is invalid or has expired.'],
            ]);
        }

        // Clear OTP from Database after verification
        $user->otp_secret = null;
        $user->save();

        // Log in the user
        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Logged in successfully.',
            'redirect' => config('fortify.home', route('dashboard', absolute: false)),
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Send WhatsApp template message using Tendigit API.
     */
    private function sendWhatsAppTemplate($licenseNumber, $apiKey, $contact, $template, $paramString)
    {
        $url = "https://app.tendigit.in/api/sendtemplate.php";
        $params = [
            'LicenseNumber' => $licenseNumber,
            'APIKey'        => $apiKey,
            'Contact'       => $contact,
            'Template'      => $template,
            'Param'         => $paramString
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => true, 'message' => $error];
        }

        curl_close($ch);
        return ['error' => false, 'response' => $response];
    }
}