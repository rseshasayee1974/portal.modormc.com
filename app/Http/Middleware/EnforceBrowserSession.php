<?php

namespace App\Http\Middleware;

use App\Services\UserPresenceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnforceBrowserSession
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            $presence = app(UserPresenceService::class);
            if ($presence->sessionExpired((int) $user->id, $request->session()->getId())) {
                Auth::guard('web')->logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return $request->expectsJson()
                    ? response()->json(['message' => 'Your browser session has ended. Please log in again.'], 401)
                    : redirect()->route('login');
            }
            // Keep a reload alive while the new HTML/JavaScript is still loading.
            if ($request->isMethod('GET') && !$request->header('X-Inertia')
                && str_contains((string) $request->header('Accept'), 'text/html')) {
                $presence->record((int) $user->id, $request->session()->getId(), 'navigation', now()->timestamp);
            }
        }
        return $next($request);
    }
}
