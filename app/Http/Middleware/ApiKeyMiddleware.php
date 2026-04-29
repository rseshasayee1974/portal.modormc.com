<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();

        if (!$bearer) {
            return response()->json(['message' => 'Missing API key'], 401);
        }

        $user = User::query()->where('api_key', $bearer)->first();
        if (!$user) {
            return response()->json(['message' => 'Invalid API key'], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
