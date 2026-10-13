<?php
/*
Author: ragul-onemodo
Created: 2026-10-13 10:54:58 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Middleware;

use App\Models\EntityUser;
use App\Services\Mobile\MobileAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\{Store, ArraySessionHandler};
use Illuminate\Support\Facades\Session;
use Laravel\Sanctum\PersonalAccessToken;

class McpPlantContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && (!$user->lockout_until || $user->lockout_until->isPast()), 403, 'Account inactive or locked.');
        abort_unless($user->currentAccessToken() instanceof PersonalAccessToken && $user->tokenCan('erp-mcp'), 403, 'An ERP MCP bearer token is required.');
        if ($origin = $request->header('Origin')) {
            $allowed = array_merge([rtrim((string)config('app.url'), '/')], config('erp_mcp.allowed_origins', []));
            abort_unless(in_array(rtrim($origin, '/'), $allowed, true), 403, 'Origin is not allowed.');
        }
        $data = validator(['plant_id'=>$request->header('X-Plant-Id')], ['plant_id'=>'required|integer|min:1'])->validate();
        $plant = MobileAccess::plants($user)->find($data['plant_id']);
        abort_unless($plant, 403, 'No access to this active plant.');
        $request->attributes->set('mobile_system_admin', $user->isSystemAdmin());
        $request->attributes->set('mobile_plant_id', $plant->id);
        $request->attributes->set('mobile_role', EntityUser::forContext($user->id, $plant->entity_id, $plant->id)?->role);
        $previous = app('session.store');
        $previousFacade = Session::getFacadeRoot();
        $store = new Store('erp-mcp', new ArraySessionHandler(120));
        $store->put(['active_plant_id'=>$plant->id, 'active_entity_id'=>$plant->entity_id, 'gstin'=>$plant->gstin]);
        app()->instance('session.store', $store);
        Session::swap($store);
        $request->setLaravelSession($store);
        try { return $next($request); }
        finally {
            app()->instance('session.store', $previous);
            Session::swap($previousFacade);
        }
    }
}
