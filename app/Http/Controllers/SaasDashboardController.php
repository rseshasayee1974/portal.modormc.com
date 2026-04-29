<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SaasDashboardController extends Controller
{
    private function canManageBilling(User $user): bool
    {
        $roles = $user->getRoleNames()->map(fn ($r) => mb_strtolower((string) $r))->all();
        return in_array('saas owner', $roles, true) || in_array('platform admin', $roles, true);
    }

    private function getOrCreateApiKey(User $user): ?string
    {
        if (!$this->canManageBilling($user)) {
            return $user->api_key;
        }

        if (!$user->api_key) {
            $user->forceFill(['api_key' => Str::random(60)])->save();
        }

        return $user->api_key;
    }

    public function dashboard(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Saas/Dashboard', [
            'apiKey' => $this->getOrCreateApiKey($user),
            'plan' => $user->plan,
            'canManageBilling' => $this->canManageBilling($user),
            'entityId' => session('active_entity_id'),
            'plantId' => session('active_plant_id'),
        ]);
    }

    public function billing(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Saas/BillingHistory', [
            'apiKey' => $this->getOrCreateApiKey($user),
            'canManageBilling' => $this->canManageBilling($user),
            'entityId' => session('active_entity_id'),
            'plantId' => session('active_plant_id'),
        ]);
    }
}
