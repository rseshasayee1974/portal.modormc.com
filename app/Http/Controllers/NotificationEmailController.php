<?php

namespace App\Http\Controllers;

use App\Models\NotificationEmail;
use App\Models\Plant;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Concerns\AuthorizesModule;

class NotificationEmailController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'setting';
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorizeModule('menu');
        $user = Auth::user();
        if ($user->hasRole('Super Administrator')) {
            $allowedPlantIds = Plant::pluck('id')->toArray();
        } else {
            $allowedPlantIds = $user->entityUsers()->pluck('plant_id')->filter()->toArray();
        }

        $query = NotificationEmail::query()
            ->whereIn('plant_id', $allowedPlantIds)
            ->with(['plant']);

        $notificationEmails = $query->orderBy('id', 'desc')->get();
        $plants = Plant::whereIn('id', $allowedPlantIds)->select('id', 'name')->get();
        
        // Assuming role name is based on standard roles
        $roles = Role::select('name')->distinct()->get()->pluck('name');

        return Inertia::render('NotificationEmails/Index', [
            'notificationEmails' => $notificationEmails,
            'plants' => $plants,
            'roles' => $roles,
            'types' => ['Purchase Order','Batching','Quotation','Customer PO', 'Invoice', 'Dispatch', 'Payment', 'Other'] // Example notification types
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'type' => 'required|string|max:40',
            'role_name' => 'required|string|max:40',
            'email' => 'required|email|max:100',
            'status' => 'required|integer|in:0,1',
        ]);

        NotificationEmail::create(array_merge($validated, [
            'plant_id' => session('active_plant_id'),
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]));

        return redirect()->back()->with('success', 'Notification email created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, NotificationEmail $notificationemail)
    {
        $this->authorizeModule('edit');
        $validated = $request->validate([
            'type' => 'required|string|max:40',
            'role_name' => 'required|string|max:40',
            'email' => 'required|email|max:100',
            'status' => 'required|integer|in:0,1',
        ]);

        $notificationemail->update(array_merge($validated, [
            'updated_by' => Auth::id(),
            'updated_at' => date('Y-m-d H:i:s')
        ]));

        return redirect()->back()->with('success', 'Notification email updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(NotificationEmail $notificationemail)
    {
        $this->authorizeModule('delete');
        $notificationemail->deleted_by = Auth::id();
        $notificationemail->save();
        $notificationemail->delete();

        return redirect()->back()->with('success', 'Notification email deleted successfully.');
    }
}