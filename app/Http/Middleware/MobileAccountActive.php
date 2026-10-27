<?php
/*
Author: Cipher
Created: 2026-10-24 16:41:47 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MobileAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->is_active, 403, 'Your account is inactive.');
        abort_if($user->lockout_until && $user->lockout_until->isFuture(), 403,
            'Your account is locked. Try again after the lockout expires.');

        return $next($request);
    }
}
