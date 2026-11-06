<?php

namespace App\Http\Controllers;

use App\Models\NotificationEmail;
use App\Models\Plant;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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
        if ($user->isSystemAdmin()) {
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
            'types' => ['Purchase Order','Batching','Quotation','Customer PO', NotificationEmail::SALES_ORDER, 'Invoice', 'Dispatch', 'Payment', config('machine_notifications.type'), 'Other']
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
            'role_name' => [\Illuminate\Validation\Rule::requiredIf(!in_array(strtolower(trim((string) $request->input('type'))), ['machine document expiry', 'sales order'], true)), 'nullable', 'string', 'max:40'],
            'email' => 'required|string|max:5100',
            'status' => 'required|integer|in:0,1',
        ]);

        if (in_array(strtolower(trim($validated['type'])), ['machine document expiry', 'sales order'], true)) $validated['role_name'] = '';

        $addresses = preg_split('/[\s,;]+/', strtolower($validated['email']), -1, PREG_SPLIT_NO_EMPTY);
        $validator = Validator::make(['addresses' => $addresses], [
            'addresses' => 'required|array|max:50',
            'addresses.*' => 'email|max:100',
        ], [
            'addresses.required' => 'Enter at least one email address.',
            'addresses.max' => 'Enter up to 50 email addresses at a time.',
            'addresses.*.email' => 'Email address #:position is invalid.',
            'addresses.*.max' => 'Email address #:position must be 100 characters or fewer.',
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages(['email' => $validator->errors()->first()]);
        }

        $plantId = session('active_plant_id');
        if (!$plantId) {
            throw ValidationException::withMessages(['email' => 'Select a plant before adding notification emails.']);
        }

        $added = DB::transaction(function () use ($addresses, $validated, $plantId) {
            Plant::whereKey($plantId)->lockForUpdate()->firstOrFail();
            $existing = NotificationEmail::where('plant_id', $plantId)
                ->whereRaw('LOWER(TRIM(type)) = ?', [strtolower($validated['type'])])
                ->when(!in_array(strtolower(trim($validated['type'])), ['machine document expiry', 'sales order'], true), fn ($q) => $q->whereRaw('LOWER(TRIM(role_name)) = ?', [strtolower($validated['role_name'])]))
                ->whereIn(DB::raw('LOWER(TRIM(email))'), $addresses)->pluck('email')
                ->map(fn (string $email) => strtolower(trim($email)))->all();
            $newAddresses = array_diff(array_unique($addresses), $existing);

            foreach ($newAddresses as $email) {
                NotificationEmail::create(array_replace($validated, [
                    'email' => $email, 'plant_id' => $plantId, 'created_by' => Auth::id(),
                ]));
            }

            return count($newAddresses);
        });

        $skipped = count($addresses) - $added;
        $message = sprintf('%d recipient(s) added.', $added);
        if ($skipped > 0) {
            $message .= sprintf(' %d duplicate(s) skipped.', $skipped);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, NotificationEmail $notificationemail)
    {
        $this->authorizeModule('edit');
        abort_unless($request->user()->isSystemAdmin() || (int) $notificationemail->plant_id === (int) session('active_plant_id'), 403);
        $validated = $request->validate([
            'type' => 'required|string|max:40',
            'role_name' => [\Illuminate\Validation\Rule::requiredIf(!in_array(strtolower(trim((string) $request->input('type'))), ['machine document expiry', 'sales order'], true)), 'nullable', 'string', 'max:40'],
            'email' => 'required|email|max:100',
            'status' => 'required|integer|in:0,1',
        ]);

        if (in_array(strtolower(trim($validated['type'])), ['machine document expiry', 'sales order'], true)) $validated['role_name'] = '';

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
        abort_unless(auth()->user()->isSystemAdmin() || (int) $notificationemail->plant_id === (int) session('active_plant_id'), 403);
        $notificationemail->deleted_by = Auth::id();
        $notificationemail->save();
        $notificationemail->delete();

        return redirect()->back()->with('success', 'Notification email deleted successfully.');
    }
}
