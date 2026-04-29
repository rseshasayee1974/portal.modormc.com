<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OtpController extends Controller
{
    /**
     * Show the OTP verification page.
     * Only accessible if the user is authenticated but OTP is pending.
     */
    public function show()
    {
        if (!Auth::check() || !session('otp_pending')) {
            return redirect()->route('dashboard');
        }

        $user = Auth::user();

        return Inertia::render('Auth/OtpVerify', [
            'mobile' => $user->mobile
                ? preg_replace('/(\d{2})\d+(\d{3})/', '$1****$2', $user->mobile)
                : null,
        ]);
    }

    /**
     * Verify the submitted OTP.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|digits:6',
        ]);

        if (!Auth::check() || !session('otp_pending')) {
            return redirect()->route('login');
        }

        $storedOtp     = session('otp_code');
        $otpExpiresAt  = session('otp_expires_at');

        // Expiry check
        if (!$storedOtp || now()->isAfter($otpExpiresAt)) {
            return back()->withErrors(['otp' => 'Your OTP has expired. Please log in again.']);
        }

        // Code mismatch
        if ($request->otp !== (string) $storedOtp) {
            return back()->withErrors(['otp' => 'Invalid OTP. Please try again.']);
        }

        // OTP verified — clear pending flags and mark as verified
        session()->forget(['otp_pending', 'otp_code', 'otp_expires_at']);
        session(['otp_verified' => true]);

        return redirect()->route('dashboard');
    }

    /**
     * Resend a fresh OTP for the current authenticated user.
     */
    public function resend()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $otp = random_int(100000, 999999);

        session([
            'otp_pending'    => true,
            'otp_code'       => $otp,
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        // TODO: dispatch SMS/Email notification here with $otp
        // Example: Notification::send(Auth::user(), new OtpNotification($otp));

        return back()->with('otp_resent', true);
    }
}
