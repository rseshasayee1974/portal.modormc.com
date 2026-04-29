<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PricingService;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ModuleApiController extends Controller
{
    public function __construct(
        private readonly UsageService $usageService,
        private readonly PricingService $pricingService
    ) {
    }

    public function chat(Request $request): JsonResponse
    {
        return $this->handleModule($request, 'chat');
    }

    public function image(Request $request): JsonResponse
    {
        return $this->handleModule($request, 'image');
    }

    public function search(Request $request): JsonResponse
    {
        return $this->handleModule($request, 'search');
    }

    private function handleModule(Request $request, string $module): JsonResponse
    {
        if (!Schema::hasColumns('usage_logs', ['entity_id', 'plant_id']) || !Schema::hasColumns('usage_summaries', ['entity_id', 'plant_id'])) {
            return response()->json([
                'message' => 'Database migration pending for SaaS entity/plant scope. Please run php artisan migrate.',
            ], 503);
        }

        $data = $request->validate([
            'input' => ['required', 'string', 'max:10000'],
            'entity_id' => ['required', 'integer', 'exists:mm_entities,id'],
            'plant_id' => ['nullable', 'integer', 'exists:mm_plants,id'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $entityId = (int) $data['entity_id'];
        $plantId = isset($data['plant_id']) ? (int) $data['plant_id'] : null;

        if (!$this->usageService->validateContextAccess($user, $entityId, $plantId)) {
            return response()->json(['message' => 'Invalid entity/plant access'], 403);
        }

        $tokens = $this->usageService->estimateTokens($data['input']);
        $plan = $this->usageService->checkPlanThreshold($user, $entityId, $plantId, $tokens);

        if ($plan['is_blocked']) {
            return response()->json([
                'message' => 'Plan limit exceeded. Upgrade your plan.',
                'plan' => $plan,
            ], 402);
        }

        $this->usageService->track($user, $entityId, $plantId, $module, $tokens, '/api/'.$module);
        $pricing = $this->pricingService->calculate($module, $tokens);

        return response()->json([
            'module' => $module,
            'user_id' => $user->id,
            'entity_id' => $entityId,
            'plant_id' => $plantId,
            'tokens_used' => $tokens,
            'pricing' => $pricing,
            'usage_alert' => $plan['alert_80'],
            'message' => ucfirst($module).' request processed successfully',
        ]);
    }
}
