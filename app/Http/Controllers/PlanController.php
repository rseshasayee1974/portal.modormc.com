<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class PlanController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'plans';
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('Plans/Index', [
            'plans' => Plan::with('planFeatures.feature')->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'plan_type' => 'required|string|max:100|unique:mm_plans,plan_type',
            'price_monthly' => 'required|numeric|min:0',
            'monthly_plan_description' => 'nullable|string',
            'price_yearly' => 'required|numeric|min:0',
            'yearly_plan_description' => 'nullable|string',
            'max_users' => 'required|integer|min:1',
            'features_json' => 'nullable|array',
            'is_active' => 'sometimes|boolean'
        ]);

        if (!isset($validated['is_active'])) {
            $validated['is_active'] = 1;
        }

        $plan = Plan::create($validated);
        $this->syncPlanFeatures($plan, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'plan' => $plan->fresh('planFeatures.feature'),
                'message' => 'Plan created successfully.'
            ]);
        }

        return redirect()->route('plans.index')->with('success', 'Plan created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Plan $plan)
    {
        $this->authorizeModule('edit');
        $validated = $request->validate([
            'plan_type' => 'required|string|max:100|unique:mm_plans,plan_type,' . $plan->id,
            'price_monthly' => 'required|numeric|min:0',
            'monthly_plan_description' => 'nullable|string',
            'price_yearly' => 'required|numeric|min:0',
            'yearly_plan_description' => 'nullable|string',
            'max_users' => 'required|integer|min:1',
            'features_json' => 'nullable|array',
            'is_active' => 'sometimes|boolean'
        ]);

        // Default to active if omitted
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = 0; // If they uncheck a switch, it might not send, wait, axios sends false
        }

        $plan->update($validated);
        $this->syncPlanFeatures($plan, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'plan' => $plan->fresh('planFeatures.feature'),
                'message' => 'Plan updated successfully.'
            ]);
        }

        return redirect()->route('plans.index')->with('success', 'Plan updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Plan $plan)
    {
        $this->authorizeModule('delete');
        $plan->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Plan deleted successfully.'
            ]);
        }

        return redirect()->route('plans.index')->with('success', 'Plan deleted successfully.');
    }

    protected function syncPlanFeatures(Plan $plan, array $validated): void
    {
        $featureValues = $this->parseFeatureValues($validated);

        $features = Feature::query()
            ->whereIn('code', array_keys($featureValues))
            ->get()
            ->keyBy('code');

        $syncedFeatureIds = [];

        foreach ($featureValues as $featureCode => $featureValue) {
            $feature = $features->get($featureCode);

            if (!$feature) {
                continue;
            }

            $syncedFeatureIds[] = $feature->id;

            $plan->planFeatures()->updateOrCreate(
                ['feature_id' => $feature->id],
                ['value' => $featureValue]
            );
        }

        $plan->planFeatures()
            ->when($syncedFeatureIds !== [], fn ($query) => $query->whereNotIn('feature_id', $syncedFeatureIds))
            ->when($syncedFeatureIds === [], fn ($query) => $query)
            ->delete();
    }

    protected function parseFeatureValues(array $validated): array
    {
        $featureValues = [
            'MAX_USERS' => (string) ($validated['max_users'] ?? 0),
        ];

        foreach ($validated['features_json'] ?? [] as $item) {
            $raw = trim((string) $item);

            if ($raw === '') {
                continue;
            }

            [$code, $value] = array_pad(explode(':', $raw, 2), 2, null);
            $code = strtoupper(trim($code));

            if ($code === '') {
                continue;
            }

            $featureValues[$code] = $value !== null && trim($value) !== ''
                ? trim($value)
                : 'true';
        }

        return $featureValues;
    }
}
