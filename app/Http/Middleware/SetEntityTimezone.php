<?php

namespace App\Http\Middleware;

use App\Helpers\DateTimeHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetEntityTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        $timezone = DateTimeHelper::getEntityTimezone();
        $request->attributes->set('entity_timezone', $timezone);

        return DateTimeHelper::inTimezone($timezone, fn () => $next($request));
    }
}
