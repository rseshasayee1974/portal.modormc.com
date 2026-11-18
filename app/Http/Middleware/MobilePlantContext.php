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
        $user = $request->user();
        if ($user && $user->isAdmin()) {
            return;
        }
        $token = $request->user()?->currentAccessToken();
        abort_unless(
            $token && (
                $token->can('sales-mobile') ||
                $token->can('*') ||
                in_array('sales-mobile', $token->abilities ?? [], true) ||
                in_array('*', $token->abilities ?? [], true)
            ),
            403,
            'Use a sales mobile device token.'
        );
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->is_active, 403, 'Your account is inactive.');
        abort_if($user->lockout_until && $user->lockout_until->isFuture(), 403, 'Your account is locked. Try again after the lockout expires.');
        $this->authorizeToken($request);

        $isAdmin = $user->isAdmin();
        $plantIdHeader = $request->header('X-Plant-Id');
        if (!$plantIdHeader && $isAdmin) {
            $plantIdHeader = MobileAccess::plants($user)->first()?->id ?? \App\Models\Plant::where('is_active', 1)->value('id');
        }

        $data = validator(['plant_id' => $plantIdHeader], ['plant_id' => 'required|integer|min:1'])->validate();
        $plant = $isAdmin
            ? (\App\Models\Plant::where('is_active', 1)->find($data['plant_id']) ?? MobileAccess::plants($user)->find($data['plant_id']))
            : MobileAccess::plants($user)->find($data['plant_id']);
        abort_unless($plant, 403, 'No access to this active plant.');
        $request->attributes->set('mobile_system_admin', $isAdmin);
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
