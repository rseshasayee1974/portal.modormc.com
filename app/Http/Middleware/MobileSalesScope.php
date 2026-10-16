<?php
/*
Author: Cipher
Created: 2026-10-15 11:55:02 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Middleware;

use App\Services\Mobile\MobileAccess;
use Closure;
use Illuminate\Http\Request;

/** Initialize visibility once, after MobilePlantContext has verified the plant. */
class MobileSalesScope
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user() && MobileAccess::plantId() > 0, 403, 'A mobile plant context is required.');

        $request->attributes->set('mobile_sales_executive', MobileAccess::isSalesExecutive());
        MobileAccess::personnelId();

        return $next($request);
    }
}
