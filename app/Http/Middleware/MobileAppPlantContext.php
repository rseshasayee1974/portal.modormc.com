<?php
/*
Author: Cipher
Created: 2026-10-21 17:51:57 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class MobileAppPlantContext extends MobilePlantContext
{
    protected function authorizeToken(Request $request): void
    {
        $token = $request->user()->currentAccessToken();
        abort_unless($token && in_array('*', $token->abilities ?? [], true), 403, 'Use a main mobile application token.');
        $request->attributes->set('mobile_app_context', true);
    }
}
