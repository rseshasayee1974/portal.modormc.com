<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Concerns\AuthorizesModule;

class LeaveApplicationController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'leave_applications';

    public function index()
    {
        $this->authorizeModule('menu');

        $activePlantId = session('active_plant_id');

        return Inertia::render('Leaves/Index', [
            'leaveApplications' => LeaveApplication::with(['personnel', 'leaveType', 'approver'])
                ->whereHas('personnel', function ($q) use ($activePlantId) {
                    $q->where('plant_id', $activePlantId);
                })
                ->latest()
                ->get(),
            'leaveTypes' => LeaveType::where('plant_id', $activePlantId)->get(),
            'personnel' => Personnel::where('plant_id', $activePlantId)->get(['id', 'first_name', 'last_name', 'employee_code']),
            'statuses' => ['pending', 'approved', 'rejected', 'cancelled']
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'personnel_id' => 'required|exists:mm_personnels,id',
            'leave_type_id' => 'required|exists:mm_leave_types,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'days' => 'required|numeric|min:0.5',
            'reason' => 'nullable|string',
            'status' => 'required|in:pending,approved,rejected,cancelled',
        ]);

        $validated['from_date'] = date('Y-m-d', strtotime($validated['from_date']));
        $validated['to_date'] = date('Y-m-d', strtotime($validated['to_date']));

        if ($validated['status'] === 'approved') {
            $validated['approved_by'] = auth()->id();
            $validated['approved_at'] = now();
        }

        LeaveApplication::create($validated);

        return redirect()->back()->with('success', 'Leave application submitted successfully.');
    }

    public function update(Request $request, LeaveApplication $leaveApplication)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'personnel_id' => 'required|exists:mm_personnels,id',
            'leave_type_id' => 'required|exists:mm_leave_types,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'days' => 'required|numeric|min:0.5',
            'reason' => 'nullable|string',
            'status' => 'required|in:pending,approved,rejected,cancelled',
        ]);

        $validated['from_date'] = date('Y-m-d', strtotime($validated['from_date']));
        $validated['to_date'] = date('Y-m-d', strtotime($validated['to_date']));

        // If status changed to approved, tag approver
        if ($validated['status'] === 'approved' && $leaveApplication->status !== 'approved') {
            $validated['approved_by'] = auth()->id();
            $validated['approved_at'] = now();
        } elseif ($validated['status'] !== 'approved') {
            $validated['approved_by'] = null;
            $validated['approved_at'] = null;
        }

        $leaveApplication->update($validated);

        return redirect()->back()->with('success', 'Leave application updated successfully.');
    }

    public function destroy(LeaveApplication $leaveApplication)
    {
        $this->authorizeModule('delete');

        $leaveApplication->delete();

        return redirect()->back()->with('success', 'Leave application deleted successfully.');
    }

    public function approve(Request $request, LeaveApplication $leaveApplication)
    {
        $this->authorizeModule('edit');

        $request->validate([
            // 'status' => 'required|in:approved,rejected,cancelled,APPROVED,REJECTED,CANCELLED,Approved,Rejected,Cancelled'
            'status' => ['required', Rule::in(['approved', 'rejected', 'cancelled','APPROVED','REJECTED','CANCELLED','Approved','Rejected','Cancelled'])]
        ]);

        $leaveApplication->update([
            'status' => $request->status,
            'approved_by' => $request->status === 'approved' ? auth()->id() : null,
            'approved_at' => $request->status === 'approved' ? now() : null,
        ]);

        return redirect()->back()->with('success', 'Leave status updated successfully.');
    }
}
