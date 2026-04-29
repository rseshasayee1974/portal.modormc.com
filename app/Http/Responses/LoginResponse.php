<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * After a successful Fortify login, check if the user has OTP enabled.
     * If so, generate an OTP, store it in session, and redirect to the verification page.
     * Otherwise, redirect to the normal dashboard/entity-select flow.
     */
    public function toResponse($request)
    {
        $user = Auth::user();

        if ($user->is_otp_enabled) {
            // Generate a 6-digit OTP and store it in the session for 5 minutes
            $otp = random_int(100000, 999999);

            session([
                'otp_pending'    => true,
                'otp_code'       => $otp,
                'otp_expires_at' => now()->addMinutes(5),
            ]);

            // TODO: Send OTP to user's mobile via your SMS provider
            // Example: $user->notify(new OtpNotification($otp));

            // Log OTP in local/dev environment for easy retrieval
            if (app()->environment('local')) {
                Log::info("[OTP] User {$user->email} — code: {$otp} (expires in 5 min)");
            }
            // For development: the OTP will be displayed on the verification page

            return redirect()->route('otp.show');
        }

        // No OTP required — normal post-login redirect
        return redirect()->intended(config('fortify.home', '/dashboard'));
    }
}
