<?php
/*
Author: Cipher
Created: 2026-11-13 10:48:11 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->is('api/*') ? $request->user('sanctum') : $request->user('web');
        $webUser = $request->user('web');
        if ($user && !$user->is_active) {
            $token = $user->currentAccessToken();
            if ($token && method_exists($token, 'delete')) $token->delete();
            if ((int) $webUser?->id === (int) $user->id) Auth::guard('web')->logout();
            if ($request->hasSession() && (int) $webUser?->id === (int) $user->id) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Your account is inactive. Contact an administrator.'], 403);
            }

            return redirect()->route('login')->withErrors(['email' => 'Your account is inactive. Contact an administrator.']);
        }

        return $next($request);
    }
}
