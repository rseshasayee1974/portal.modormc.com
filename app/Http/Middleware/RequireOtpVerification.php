<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireOtpVerification
{
    /**
     * If the authenticated user has OTP enabled and hasn't verified it yet,
     * redirect them to the OTP verification page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Allow logout even while OTP verification is pending.
            if ($request->routeIs('otp.*', 'logout')) {
                return $next($request);
            }

            if ($user->is_otp_enabled && session('otp_pending')) {
                return redirect()->route('otp.show');
            }
        }

        return $next($request);
    }
}
