<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class SubscriptionStatusController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'subscription_statuses';
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('SubscriptionStatuses/Index', [
            'subscriptionStatuses' => SubscriptionStatus::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'status_name' => 'required|string|max:100|unique:mm_subscription_statuses,status_name',
        ]);

        $subscriptionStatus = SubscriptionStatus::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'subscriptionStatus' => $subscriptionStatus,
                'message' => 'Subscription Status created successfully.'
            ]);
        }

        return redirect()->route('subscriptionstatuses.index')->with('success', 'Subscription Status created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SubscriptionStatus $subscriptionStatus)
    {
        $this->authorizeModule('edit');
        $validated = $request->validate([
            'status_name' => 'required|string|max:100|unique:mm_subscription_statuses,status_name,' . $subscriptionStatus->id,
        ]);

        $subscriptionStatus->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'subscriptionStatus' => $subscriptionStatus,
                'message' => 'Subscription Status updated successfully.'
            ]);
        }

        return redirect()->route('subscriptionstatuses.index')->with('success', 'Subscription Status updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SubscriptionStatus $subscriptionStatus)
    {
        $this->authorizeModule('delete');
        $subscriptionStatus->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Subscription Status deleted successfully.'
            ]);
        }

        return redirect()->route('subscriptionstatuses.index')->with('success', 'Subscription Status deleted successfully.');
    }
}
