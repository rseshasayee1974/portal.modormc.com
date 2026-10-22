<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 17:59:50 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Middleware;

use App\Models\EntityUser;
use App\Services\Mobile\MobileAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\{Store, ArraySessionHandler};
use Illuminate\Support\Facades\Session;

class MobilePlantContext
{
    protected function authorizeToken(Request $request): void
    {
        abort_unless($request->user()->currentAccessToken() && $request->user()->tokenCan('sales-mobile'), 403, 'Use a sales mobile device token.');
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->is_active, 403, 'Your account is inactive.');
        $this->authorizeToken($request);
        $data = validator(['plant_id' => $request->header('X-Plant-Id')], ['plant_id' => 'required|integer|min:1'])->validate();
        $plant = MobileAccess::plants($user)->find($data['plant_id']);
        abort_unless($plant, 403, 'No access to this active plant.');
        $request->attributes->set('mobile_system_admin', $user->isSystemAdmin());
        $request->attributes->set('mobile_plant_id', $plant->id);
        $role = EntityUser::forContext($user->id, $plant->entity_id, $plant->id)?->role;
        $request->attributes->set('mobile_role', $role);

        // Existing domain code reads session context. Use an ephemeral store; never change browser state or user defaults.
        $previous = app('session.store');
        $previousFacade = Session::getFacadeRoot();
        $store = new Store('sales-mobile', new ArraySessionHandler(120));
        $store->put(['active_plant_id' => $plant->id, 'active_entity_id' => $plant->entity_id, 'gstin' => $plant->gstin]);
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
