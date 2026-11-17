<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:05:42 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class MobileDeviceToken
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && (!$user->lockout_until || $user->lockout_until->isPast()), 403, 'Account inactive or locked.');

        $isAdmin = $user->isAdmin();

        // Admin can use the sales-mobile without any restrictions
        if (!$isAdmin) {
            abort_unless($user->is_otp_enabled, 403, 'Mobile app access has not been granted or has been removed. Contact your administrator.');
        }

        $token = $user->currentAccessToken();
        abort_unless(
            $isAdmin || (
                $token instanceof PersonalAccessToken && (
                    $token->can('sales-mobile') ||
                    $token->can('*') ||
                    in_array('sales-mobile', $token->abilities ?? [], true) ||
                    in_array('*', $token->abilities ?? [], true)
                )
            ),
            403,
            'Use a sales mobile bearer token.'
        );

        return $next($request);
    }
}
