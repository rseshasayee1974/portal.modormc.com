<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\EntityUser;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class BillingApiController extends Controller
{
    private function formatBilling(Billing $billing): array
    {
        $breakdown = collect($billing->breakdown_json ?? [])->map(function ($item, $module) {
            return [
                'module' => (string) $module,
                'tokens' => (int) ($item['tokens'] ?? 0),
                'requests' => (int) ($item['requests'] ?? 0),
                'cost' => (float) ($item['cost'] ?? 0),
            ];
        })->values();

        return [
            'id' => $billing->id,
            'month' => $billing->month,
            'total_amount' => (float) $billing->total_amount,
            'status' => $billing->status,
            'module_breakdown' => $breakdown,
        ];
    }

    public function __construct(private readonly BillingService $billingService)
    {
    }

    private function authorizeBillingAccess(User $user): void
    {
        $roles = $user->getRoleNames()->map(fn ($r) => mb_strtolower((string) $r))->all();
        $allowed = in_array('saas owner', $roles, true) || in_array('platform admin', $roles, true);
        abort_unless($allowed, 403, 'Only SaaS Owner or Platform Admin can access billing APIs.');
    }

    private function resolveContext(Request $request, User $user): array
    {
        $data = $request->validate([
            'entity_id' => ['required', 'integer', 'exists:mm_entities,id'],
            'plant_id' => ['nullable', 'integer', 'exists:mm_plants,id'],
        ]);

        $entityId = (int) $data['entity_id'];
        $plantId = isset($data['plant_id']) ? (int) $data['plant_id'] : null;

        $access = EntityUser::query()
            ->where('user_id', $user->id)
            ->where('entity_id', $entityId)
            ->when($plantId !== null, fn ($q) => $q->where('plant_id', $plantId))
            ->exists();

        abort_unless($access, 403, 'Invalid entity/plant access');

        return [$entityId, $plantId];
    }

    public function generate(Request $request): JsonResponse
    {
        if (!Schema::hasColumns('billings', ['entity_id', 'plant_id']) || !Schema::hasColumns('usage_summaries', ['entity_id', 'plant_id'])) {
            return response()->json([
                'message' => 'Database migration pending for SaaS entity/plant scope. Please run php artisan migrate.',
            ], 503);
        }

        /** @var User $user */
        $user = $request->user();
        $this->authorizeBillingAccess($user);
        [$entityId, $plantId] = $this->resolveContext($request, $user);
        $month = $request->input('month', now()->format('Y-m'));
        $billing = $this->billingService->generateMonthlyBilling($user, $entityId, $plantId, $month);

        return response()->json([
            'message' => 'Billing generated successfully',
            'billing' => $this->formatBilling($billing),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        if (!Schema::hasColumns('billings', ['entity_id', 'plant_id'])) {
            return response()->json([
                'message' => 'Database migration pending for SaaS entity/plant scope. Please run php artisan migrate.',
            ], 503);
        }

        /** @var User $user */
        $user = $request->user();
        $this->authorizeBillingAccess($user);
        [$entityId, $plantId] = $this->resolveContext($request, $user);

        $query = Billing::query()
            ->where('user_id', $user->id)
            ->where('entity_id', $entityId);

        if ($plantId === null) {
            $query->whereNull('plant_id');
        } else {
            $query->where('plant_id', $plantId);
        }

        $billings = $query->latest('month')->get();

        return response()->json([
            'data' => $billings->map(fn (Billing $billing) => $this->formatBilling($billing))->values(),
        ]);
    }

    public function mockPay(Request $request, Billing $billing): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeBillingAccess($user);
        [$entityId, $plantId] = $this->resolveContext($request, $user);
        abort_unless(
            $billing->user_id === $user->id
            && (int) $billing->entity_id === $entityId
            && (int) ($billing->plant_id ?? 0) === (int) ($plantId ?? 0),
            403
        );

        $data = $request->validate([
            'success' => ['required', 'boolean'],
        ]);

        $billing->status = $data['success'] ? 'paid' : 'pending';
        $billing->save();

        return response()->json([
            'message' => $data['success'] ? 'Payment successful' : 'Payment failed',
            'billing' => $billing,
        ]);
    }
}
