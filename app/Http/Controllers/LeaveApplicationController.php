<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\Personnel;
use App\Models\EmployeeLeaveBalance;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Concerns\AuthorizesModule;

class LeaveApplicationController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'leave_application';

    public function index()
    {
        $this->authorizeModule('menu');

        $activePlantId = session('active_plant_id');
        $currentYear = (int)date('Y');

        $personnelList = Personnel::where('plant_id', $activePlantId)->get(['id', 'first_name', 'last_name', 'employee_code']);
        $leaveTypesList = LeaveType::orderBy('name', 'asc')->get();

        // Ensure current year leave balances exist for plant personnel
        if ($personnelList->isNotEmpty() && $leaveTypesList->isNotEmpty()) {
            foreach ($personnelList as $person) {
                foreach ($leaveTypesList as $type) {
                    EmployeeLeaveBalance::syncBalance((int)$person->id, (int)$type->id, $currentYear);
                }
            }
        }

        return Inertia::render('Leaves/Index', [
            'leaveApplications' => LeaveApplication::with(['personnel', 'leaveType', 'approver'])
                ->whereHas('personnel', function ($q) use ($activePlantId) {
                    $q->where('plant_id', $activePlantId);
                })
                ->latest()
                ->get(),
            'leaveTypes' => $leaveTypesList,
            'personnel' => $personnelList,
            'statuses' => ['pending', 'approved', 'rejected', 'cancelled'],
            'leaveBalances' => EmployeeLeaveBalance::with(['personnel', 'leaveType'])
                ->whereHas('personnel', function ($q) use ($activePlantId) {
                    $q->where('plant_id', $activePlantId);
                })
                ->where('year', $currentYear)
                ->get(),
        ]);
    }

    public function storeBalance(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'personnel_id' => 'required|exists:mm_personnels,id',
            'leave_type_id' => 'required|exists:mm_leave_types,id',
            'year' => 'required|integer|min:2000|max:2099',
            'opening_balance' => 'required|numeric|min:0',
            'accrued' => 'required|numeric|min:0',
        ]);

        $usedDays = (float) LeaveApplication::where('personnel_id', $validated['personnel_id'])
            ->where('leave_type_id', $validated['leave_type_id'])
            ->where('status', 'approved')
            ->whereYear('from_date', $validated['year'])
            ->sum('days');

        $netBalance = max(0.0, ((float)$validated['opening_balance'] + (float)$validated['accrued']) - $usedDays);

        EmployeeLeaveBalance::updateOrCreate(
            [
                'personnel_id' => $validated['personnel_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'year' => $validated['year'],
            ],
            [
                'opening_balance' => $validated['opening_balance'],
                'accrued' => $validated['accrued'],
                'used' => $usedDays,
                'balance' => round($netBalance, 2),
            ]
        );

        return redirect()->back()->with('success', 'Employee leave balance saved successfully.');
    }

    public function updateBalance(Request $request, EmployeeLeaveBalance $leaveBalance)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'personnel_id' => 'required|exists:mm_personnels,id',
            'leave_type_id' => 'required|exists:mm_leave_types,id',
            'year' => 'required|integer|min:2000|max:2099',
            'opening_balance' => 'required|numeric|min:0',
            'accrued' => 'required|numeric|min:0',
        ]);

        $usedDays = (float) LeaveApplication::where('personnel_id', $validated['personnel_id'])
            ->where('leave_type_id', $validated['leave_type_id'])
            ->where('status', 'approved')
            ->whereYear('from_date', $validated['year'])
            ->sum('days');

        $netBalance = max(0.0, ((float)$validated['opening_balance'] + (float)$validated['accrued']) - $usedDays);

        $leaveBalance->update([
            'personnel_id' => $validated['personnel_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'year' => $validated['year'],
            'opening_balance' => $validated['opening_balance'],
            'accrued' => $validated['accrued'],
            'used' => $usedDays,
            'balance' => round($netBalance, 2),
        ]);

        return redirect()->back()->with('success', 'Employee leave balance updated successfully.');
    }

    public function destroyBalance(EmployeeLeaveBalance $leaveBalance)
    {
        $this->authorizeModule('delete');

        $leaveBalance->delete();

        return redirect()->back()->with('success', 'Leave balance record deleted successfully.');
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

        $app = LeaveApplication::create($validated);

        EmployeeLeaveBalance::syncBalance(
            (int)$app->personnel_id,
            (int)$app->leave_type_id,
            (int)date('Y', strtotime($app->from_date))
        );

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

        $oldPersonnelId = $leaveApplication->personnel_id;
        $oldLeaveTypeId = $leaveApplication->leave_type_id;
        $oldYear = (int)date('Y', strtotime($leaveApplication->from_date));

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

        EmployeeLeaveBalance::syncBalance((int)$oldPersonnelId, (int)$oldLeaveTypeId, $oldYear);
        EmployeeLeaveBalance::syncBalance(
            (int)$leaveApplication->personnel_id,
            (int)$leaveApplication->leave_type_id,
            (int)date('Y', strtotime($leaveApplication->from_date))
        );

        return redirect()->back()->with('success', 'Leave application updated successfully.');
    }

    public function destroy(LeaveApplication $leaveApplication)
    {
        $this->authorizeModule('delete');

        $personnelId = $leaveApplication->personnel_id;
        $leaveTypeId = $leaveApplication->leave_type_id;
        $year = (int)date('Y', strtotime($leaveApplication->from_date));

        $leaveApplication->delete();

        EmployeeLeaveBalance::syncBalance((int)$personnelId, (int)$leaveTypeId, $year);

        return redirect()->back()->with('success', 'Leave application deleted successfully.');
    }

    public function approve(Request $request, LeaveApplication $leaveApplication)
    {
        $this->authorizeModule('edit');

        $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'cancelled','APPROVED','REJECTED','CANCELLED','Approved','Rejected','Cancelled'])]
        ]);

        $normalizedStatus = strtolower($request->status);

        $leaveApplication->update([
            'status' => $normalizedStatus,
            'approved_by' => $normalizedStatus === 'approved' ? auth()->id() : null,
            'approved_at' => $normalizedStatus === 'approved' ? now() : null,
        ]);

        EmployeeLeaveBalance::syncBalance(
            (int)$leaveApplication->personnel_id,
            (int)$leaveApplication->leave_type_id,
            (int)date('Y', strtotime($leaveApplication->from_date))
        );

        return redirect()->back()->with('success', 'Leave status updated successfully.');
    }
}
