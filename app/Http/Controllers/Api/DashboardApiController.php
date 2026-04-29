<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EntityUser;
use App\Models\UsageSummary;
use App\Models\User;
use App\Services\PricingService;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DashboardApiController extends Controller
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly UsageService $usageService
    )
    {
    }

    private function canViewUsageAlert(User $user): bool
    {
        $roles = $user->getRoleNames()->map(fn ($r) => mb_strtolower((string) $r))->all();
        return in_array('saas owner', $roles, true) || in_array('platform admin', $roles, true);
    }

    public function index(Request $request): JsonResponse
    {
        if (!Schema::hasColumns('usage_summaries', ['entity_id', 'plant_id'])) {
            return response()->json([
                'message' => 'Database migration pending for SaaS entity/plant scope. Please run php artisan migrate.',
            ], 503);
        }

        $data = $request->validate([
            'entity_id' => ['required', 'integer', 'exists:mm_entities,id'],
            'plant_id' => ['nullable', 'integer', 'exists:mm_plants,id'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $entityId = (int) $data['entity_id'];
        $plantId = isset($data['plant_id']) ? (int) $data['plant_id'] : null;
        $access = EntityUser::query()
            ->where('user_id', $user->id)
            ->where('entity_id', $entityId)
            ->when($plantId !== null, fn ($q) => $q->where('plant_id', $plantId))
            ->exists();

        if (!$access) {
            return response()->json(['message' => 'Invalid entity/plant access'], 403);
        }

        $month = now()->format('Y-m');

        $query = UsageSummary::query()
            ->where('user_id', $user->id)
            ->where('entity_id', $entityId)
            ->where('month', $month);

        if ($plantId === null) {
            $query->whereNull('plant_id');
        } else {
            $query->where('plant_id', $plantId);
        }

        $rows = $query->get();

        $totalTokens = (int) $rows->sum('tokens');
        $totalRequests = (int) $rows->sum('requests');

        $moduleBreakdown = $rows->groupBy('module')->map(function ($group, $module) {
            $tokens = (int) $group->sum('tokens');
            $requests = (int) $group->sum('requests');
            $price = $this->pricingService->calculate((string) $module, $tokens);
            $cost = round($price['token_cost'] + ($price['request_cost'] * $requests), 4);

            return [
                'module' => $module,
                'tokens' => $tokens,
                'requests' => $requests,
                'cost' => $cost,
            ];
        })->values();

        $totalCost = round((float) $moduleBreakdown->sum('cost'), 4);
        $planState = $this->usageService->checkPlanThreshold($user, $entityId, $plantId, 0);

        $dailyUsage = $rows->groupBy(fn ($item) => $item->date->toDateString())
            ->map(fn ($dayRows, $date) => [
                'date' => $date,
                'tokens' => (int) $dayRows->sum('tokens'),
                'requests' => (int) $dayRows->sum('requests'),
            ])
            ->values();

        return response()->json([
            'total_tokens' => $totalTokens,
            'total_cost' => $totalCost,
            'total_requests' => $totalRequests,
            'entity_id' => $entityId,
            'plant_id' => $plantId,
            'module_breakdown' => $moduleBreakdown,
            'daily_usage' => $dailyUsage,
            'plan' => $planState['plan'],
            'token_limit' => $planState['limit'],
            'usage_percent' => $planState['usage_percent'],
            'usage_alert' => $this->canViewUsageAlert($user) ? $planState['alert_80'] : null,
        ]);
    }
}
