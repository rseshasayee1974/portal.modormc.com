<?php

namespace App\Http\Controllers;

use App\Services\UserPresenceService;
use Illuminate\Http\Request;

class UserPresenceController extends Controller
{
    public function update(Request $request, UserPresenceService $presence)
    {
        $data = $request->validate([
            'tab_id' => 'required|uuid',
            'sequence' => 'required|integer|min:1|max:2147483646',
        ]);
        $presence->record((int) $request->user()->id, $request->session()->getId(),
            $data['tab_id'], (int) $data['sequence'], $request->routeIs('session.presence.close'),
            $request->session()->get('presence_login_session'));
        return response()->noContent();
    }
}
