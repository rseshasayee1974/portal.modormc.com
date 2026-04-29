<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\PersonnelContact;
use App\Models\Patron;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PersonnelController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'personnel';

    public function index()
    {
        $this->authorizeModule('menu');
        
        return Inertia::render('Personnel/Index', [
            'personnel' => Personnel::with(['contacts', 'patrons'])
                ->where('plant_id', session('active_plant_id'))
                ->latest()
                ->get(),
            'patrons' => Patron::where('plant_id', session('active_plant_id'))
                ->where('status', true)
                ->get(['id', 'legal_name']),
            'employeeTypes' => ['Permanent', 'Contract', 'Daily Wage', 'Temporary'],
            'genders' => ['Male', 'Female', 'Other'],
            'statuses' => ['active', 'inactive', 'resigned', 'on_leave'],
            'contactTypes' => ['Phone', 'Email', 'Emergency Phone', 'WhatsApp']
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'employee_type' => 'nullable|string',
            'gender' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'joining_date' => 'nullable|date',
            'status' => 'required|string',
            
            // Contacts
            'contacts' => 'nullable|array',
            'contacts.*.contact_type' => 'required|string',
            'contacts.*.contact_value' => 'required|string',
            'contacts.*.is_primary' => 'boolean',
            
            // Patron Relations
            'patron_ids' => 'nullable|array',
            'patron_ids.*' => 'exists:mm_patrons,id',
        ]);

        DB::transaction(function () use ($validated) {
            $personnelData = collect($validated)->except(['contacts', 'patron_ids'])->toArray();
            
            // Format dates for MySQL
            if (!empty($personnelData['date_of_birth'])) {
                $personnelData['date_of_birth'] = date('Y-m-d', strtotime($personnelData['date_of_birth']));
            }
            if (!empty($personnelData['joining_date'])) {
                $personnelData['joining_date'] = date('Y-m-d', strtotime($personnelData['joining_date']));
            }

            $personnelData['plant_id'] = session('active_plant_id');
            $personnelData['entity_id'] = session('active_entity_id');
            $personnelData['created_by'] = auth()->id();
            
            $personnel = Personnel::create($personnelData);

            if (!empty($validated['contacts'])) {
                foreach ($validated['contacts'] as $contact) {
                    $contact['contact_id'] = (string) Str::uuid();
                    $personnel->contacts()->create($contact);
                }
            }

            if (!empty($validated['patron_ids'])) {
                $personnel->patrons()->sync($validated['patron_ids']);
            }
        });

        return redirect()->back()->with('success', 'Personnel record created successfully.');
    }

    public function update(Request $request, Personnel $personnel)
    {
        $this->authorizeModule('edit');
        
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'employee_type' => 'nullable|string',
            'gender' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'joining_date' => 'nullable|date',
            'status' => 'required|string',
            
            // Contacts
            'contacts' => 'nullable|array',
            'contacts.*.contact_id' => 'nullable|string',
            'contacts.*.contact_type' => 'required|string',
            'contacts.*.contact_value' => 'required|string',
            'contacts.*.is_primary' => 'boolean',
            
            // Patron Relations
            'patron_ids' => 'nullable|array',
            'patron_ids.*' => 'exists:mm_patrons,id',
        ]);

        DB::transaction(function () use ($validated, $personnel) {
            $personnelData = collect($validated)->except(['contacts', 'patron_ids'])->toArray();

            // Format dates for MySQL
            if (!empty($personnelData['date_of_birth'])) {
                $personnelData['date_of_birth'] = date('Y-m-d', strtotime($personnelData['date_of_birth']));
            }
            if (!empty($personnelData['joining_date'])) {
                $personnelData['joining_date'] = date('Y-m-d', strtotime($personnelData['joining_date']));
            }

            $personnelData['updated_by'] = auth()->id();
            $personnel->update($personnelData);

            if (isset($validated['contacts'])) {
                $contactIds = collect($validated['contacts'])->pluck('contact_id')->filter()->toArray();
                $personnel->contacts()->whereNotIn('contact_id', $contactIds)->delete();

                foreach ($validated['contacts'] as $contact) {
                    if (isset($contact['contact_id'])) {
                        PersonnelContact::where('contact_id', $contact['contact_id'])->update($contact);
                    } else {
                        $contact['contact_id'] = (string) Str::uuid();
                        $personnel->contacts()->create($contact);
                    }
                }
            }

            if (isset($validated['patron_ids'])) {
                $personnel->patrons()->sync($validated['patron_ids']);
            }
        });

        return redirect()->back()->with('success', 'Personnel record updated successfully.');
    }

    public function destroy(Personnel $personnel)
    {
        $this->authorizeModule('delete');
        
        $personnel->deleted_by = auth()->id();
        $personnel->save();
        $personnel->delete();

        return redirect()->back()->with('success', 'Personnel record deleted successfully.');
    }
}
