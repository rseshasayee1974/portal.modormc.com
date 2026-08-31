<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\PersonnelContact;
use App\Models\Patron;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PersonnelController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'personnel';

    public function index()
    {
        $this->authorizeModule('menu');
        
        $activePlantId = session('active_plant_id');

        return Inertia::render('Personnel/Index', [
            'personnel' => Personnel::with(['contacts', 'patrons', 'department', 'designation', 'reportingManager', 'salaryStructures.salaryComponent'])
                ->where('plant_id', $activePlantId)
                ->latest()
                ->get(),
            'patrons' => Patron::where('plant_id', $activePlantId)
                ->where('status', true)
                ->orderBy('legal_name', 'asc')
                ->get(['id', 'legal_name']),
            'departments' => Department::orderBy('name', 'asc')
                ->get(['id', 'name', 'code']),
            'designations' => Designation::orderBy('name', 'asc')
                ->get(['id', 'name', 'code']),
            'managers' => Personnel::where('plant_id', $activePlantId)
                ->where('status', 'active')
                ->orderBy('first_name', 'asc')
                ->get(['id', 'first_name', 'last_name', 'employee_code']),
            'employmentTypes' => ['permanent', 'contract', 'trainee', 'temporary', 'consultant'],
            'genders' => ['male', 'female', 'other'],
            'statuses' => ['active', 'inactive', 'terminated', 'resigned', 'retired'],
            'contactTypes' => ['Phone', 'Email', 'Emergency Phone', 'WhatsApp'],
            'salaryComponents' => \App\Models\SalaryComponent::query()->where('plant_id', $activePlantId)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'type']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        
        $activePlantId = session('active_plant_id');

        $validated = $request->validate([
            'employee_code' => ['nullable', 'string', Rule::unique('mm_personnels', 'employee_code')->where('plant_id', $activePlantId)],
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'department_id' => 'nullable|exists:mm_departments,id',
            'designation_id' => 'nullable|exists:mm_designations,id',
            'reporting_manager_id' => 'nullable|exists:mm_personnels,id',
            'email' => 'nullable|email|unique:mm_personnels,email',
            'mobile' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'joining_date' => 'nullable|date',
            'exit_date' => 'nullable|date',
            'gender' => 'nullable',
            'employment_type' => 'required',
            'status' => 'required',
            'pan' => 'nullable|string|unique:mm_personnels,pan',
            'aadhaar' => 'nullable|string|unique:mm_personnels,aadhaar',
            'uan' => 'nullable|string',
            'esi_number' => 'nullable|string',
            'bank_account_no' => 'nullable|string',
            'bank_ifsc' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'photo' => 'nullable|string',
            'meta' => 'nullable|array',
            
            // Contacts pivot/relations
            'contacts' => 'nullable|array',
            'contacts.*.contact_type' => 'required|string',
            'contacts.*.contact_value' => 'required|string',
            'contacts.*.is_primary' => 'boolean',
            
            // Patron relations
            'patron_ids' => 'nullable|array',
            'patron_ids.*' => 'exists:mm_patrons,id',
            
            // Salary structures
            'salary_structures' => 'nullable|array',
            'salary_structures.*.salary_component_id' => 'required|exists:mm_salary_components,id',
            'salary_structures.*.amount' => 'required|numeric|min:0',
            'salary_structures.*.effective_from' => 'required|date',
            'salary_structures.*.effective_to' => 'nullable|date',
        ]);

        DB::transaction(function () use ($validated, $activePlantId) {
            $personnelData = collect($validated)->except(['contacts', 'patron_ids', 'salary_structures'])->toArray();
            
            // Format dates
            if (!empty($personnelData['date_of_birth'])) {
                $personnelData['date_of_birth'] = date('Y-m-d', strtotime($personnelData['date_of_birth']));
            }
            if (!empty($personnelData['joining_date'])) {
                $personnelData['joining_date'] = date('Y-m-d', strtotime($personnelData['joining_date']));
            }
            if (!empty($personnelData['exit_date'])) {
                $personnelData['exit_date'] = date('Y-m-d', strtotime($personnelData['exit_date']));
            }

            $personnelData['plant_id'] = $activePlantId;
            $personnelData['entity_id'] = session('active_entity_id');
            $personnelData['created_by'] = auth()->id();
            
            if (empty($personnelData['employee_code'])) {
                $personnelData['employee_code'] = Personnel::generateNextEmployeeCode($activePlantId);
            }

            $personnel = Personnel::create($personnelData);

            if (!empty($validated['contacts'])) {
                foreach ($validated['contacts'] as $contact) {
                    $contactRecord = \App\Models\Contact::create([
                        'plant_id' => $personnel->plant_id,
                        'name' => trim($personnel->first_name . ' ' . $personnel->last_name),
                        'mobile' => $contact['contact_type'] === 'Phone' || $contact['contact_type'] === 'Mobile' ? $contact['contact_value'] : '',
                        'email' => $contact['contact_type'] === 'Email' ? $contact['contact_value'] : '',
                        'contact_type_id' => 1,
                        'is_primary' => $contact['is_primary'] ?? 0,
                        'status' => 1,
                    ]);
                    $contact['contact_id'] = (string) $contactRecord->id;
                    $personnel->contacts()->create($contact);
                }
            }

            if (!empty($validated['patron_ids'])) {
                $personnel->patrons()->sync($validated['patron_ids']);
            }

            if (!empty($validated['salary_structures'])) {
                foreach ($validated['salary_structures'] as $struct) {
                    if (!empty($struct['effective_from'])) {
                        $struct['effective_from'] = date('Y-m-d', strtotime($struct['effective_from']));
                    }
                    if (!empty($struct['effective_to'])) {
                        $struct['effective_to'] = date('Y-m-d', strtotime($struct['effective_to']));
                    }
                    $personnel->salaryStructures()->create($struct);
                }
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
            'department_id' => 'nullable|exists:mm_departments,id',
            'designation_id' => 'nullable|exists:mm_designations,id',
            'reporting_manager_id' => 'nullable|exists:mm_personnels,id',
            'employee_code' => ['nullable', 'string', Rule::unique('mm_personnels', 'employee_code')->where('plant_id', $personnel->plant_id)->ignore($personnel->id)],
            'email' => ['nullable', 'email', Rule::unique('mm_personnels')->ignore($personnel->id)],
            'mobile' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'joining_date' => 'nullable|date',
            'exit_date' => 'nullable|date',
            'gender' => 'nullable',
            'employment_type' => 'required',
            'status' => 'required',
            'pan' => ['nullable', 'string', Rule::unique('mm_personnels')->ignore($personnel->id)],
            'aadhaar' => ['nullable', 'string', Rule::unique('mm_personnels')->ignore($personnel->id)],
            'uan' => 'nullable|string',
            'esi_number' => 'nullable|string',
            'bank_account_no' => 'nullable|string',
            'bank_ifsc' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'photo' => 'nullable|string',
            'meta' => 'nullable|array',
            
            // Contacts pivot/relations
            'contacts' => 'nullable|array',
            'contacts.*.contact_id' => 'nullable|string',
            'contacts.*.contact_type' => 'required|string',
            'contacts.*.contact_value' => 'required|string',
            'contacts.*.is_primary' => 'boolean',
            
            // Patron relations
            'patron_ids' => 'nullable|array',
            'patron_ids.*' => 'exists:mm_patrons,id',
            
            // Salary structures
            'salary_structures' => 'nullable|array',
            'salary_structures.*.id' => 'nullable|exists:mm_employee_salary_structures,id',
            'salary_structures.*.salary_component_id' => 'required|exists:mm_salary_components,id',
            'salary_structures.*.amount' => 'required|numeric|min:0',
            'salary_structures.*.effective_from' => 'required|date',
            'salary_structures.*.effective_to' => 'nullable|date',
        ]);

        DB::transaction(function () use ($validated, $personnel) {
            $personnelData = collect($validated)->except(['contacts', 'patron_ids', 'salary_structures', 'employee_code'])->toArray();

            // Convert empty strings to null for unique or nullable columns to prevent duplicate key or type errors in the DB
            foreach (['email', 'pan', 'aadhaar', 'mobile', 'uan', 'esi_number', 'bank_account_no', 'bank_ifsc', 'bank_name', 'last_name', 'gender'] as $field) {
                if (array_key_exists($field, $personnelData) && ($personnelData[$field] === '' || $personnelData[$field] === null)) {
                    $personnelData[$field] = null;
                }
            }

            // Format dates
            if (!empty($personnelData['date_of_birth'])) {
                $personnelData['date_of_birth'] = date('Y-m-d', strtotime($personnelData['date_of_birth']));
            } else {
                $personnelData['date_of_birth'] = null;
            }
            if (!empty($personnelData['joining_date'])) {
                $personnelData['joining_date'] = date('Y-m-d', strtotime($personnelData['joining_date']));
            } else {
                $personnelData['joining_date'] = null;
            }
            if (!empty($personnelData['exit_date'])) {
                $personnelData['exit_date'] = date('Y-m-d', strtotime($personnelData['exit_date']));
            } else {
                $personnelData['exit_date'] = null;
            }

            $personnelData['updated_by'] = auth()->id();
            $personnel->update($personnelData);

            if (isset($validated['contacts'])) {
                $contactIds = collect($validated['contacts'])->pluck('contact_id')->filter()->toArray();
                $personnel->contacts()->whereNotIn('contact_id', $contactIds)->delete();

                foreach ($validated['contacts'] as $contact) {
                    if (isset($contact['contact_id'])) {
                        PersonnelContact::where('contact_id', $contact['contact_id'])->update([
                            'contact_type' => (string) $contact['contact_type'],
                            'contact_value' => (string) $contact['contact_value'],
                            'is_primary' => $contact['is_primary'] ?? 0,
                        ]);

                        if (is_numeric($contact['contact_id'])) {
                            \App\Models\Contact::where('id', $contact['contact_id'])->update([
                                'mobile' => $contact['contact_type'] === 'Phone' || $contact['contact_type'] === 'Mobile' ? (string) $contact['contact_value'] : '',
                                'email' => $contact['contact_type'] === 'Email' ? (string) $contact['contact_value'] : '',
                            ]);
                        }
                    } else {
                        $contactRecord = \App\Models\Contact::create([
                            'plant_id' => $personnel->plant_id,
                            'name' => trim($personnel->first_name . ' ' . $personnel->last_name),
                            'mobile' => $contact['contact_type'] === 'Phone' || $contact['contact_type'] === 'Mobile' ? (string) $contact['contact_value'] : '',
                            'email' => $contact['contact_type'] === 'Email' ? (string) $contact['contact_value'] : '',
                            'contact_type_id' => 1,
                            'is_primary' => $contact['is_primary'] ?? 0,
                            'status' => 1,
                        ]);
                        $contact['contact_id'] = (string) $contactRecord->id;
                        $personnel->contacts()->create($contact);
                    }
                }
            }

            if (isset($validated['patron_ids'])) {
                $personnel->patrons()->sync($validated['patron_ids']);
            }

            if (isset($validated['salary_structures'])) {
                $activeStructures = $personnel->salaryStructures()->get();
                
                // Track the old structure state
                $oldStructureJson = $activeStructures->map(function($s) {
                    return [
                        'salary_component_id' => $s->salary_component_id,
                        'amount' => number_format((float)$s->amount, 2, '.', ''),
                        'effective_from' => $s->effective_from ? $s->effective_from->format('Y-m-d') : null,
                        'effective_to' => $s->effective_to ? $s->effective_to->format('Y-m-d') : null,
                    ];
                })->values()->toArray();

                $activeIds = $activeStructures->pluck('id')->toArray();
                $submittedIds = collect($validated['salary_structures'])->pluck('id')->filter()->toArray();
                
                $hasRevisionChanges = false;

                // 1. Soft delete active structures that are no longer in the submitted list
                $idsToRemove = array_diff($activeIds, $submittedIds);
                if (!empty($idsToRemove)) {
                    $hasRevisionChanges = true;
                    \App\Models\EmployeeSalaryStructure::whereIn('id', $idsToRemove)->get()->each(function($item) {
                        $item->delete();
                    });
                }

                // 2. Process submitted allocations: insert new ones, or soft-delete and insert if changed
                foreach ($validated['salary_structures'] as $struct) {
                    $structComponentId = $struct['salary_component_id'];
                    $structAmount = number_format((float)$struct['amount'], 2, '.', '');
                    $effectiveFrom = !empty($struct['effective_from']) ? date('Y-m-d', strtotime($struct['effective_from'])) : null;
                    $effectiveTo = !empty($struct['effective_to']) ? date('Y-m-d', strtotime($struct['effective_to'])) : null;

                    if (isset($struct['id'])) {
                        $existing = \App\Models\EmployeeSalaryStructure::find($struct['id']);
                        if ($existing) {
                            $existingAmount = number_format((float)$existing->amount, 2, '.', '');
                            $existingFrom = $existing->effective_from ? $existing->effective_from->format('Y-m-d') : null;
                            $existingTo = $existing->effective_to ? $existing->effective_to->format('Y-m-d') : null;

                            $hasChanges = (
                                $existing->salary_component_id != $structComponentId ||
                                $existingAmount != $structAmount ||
                                $existingFrom != $effectiveFrom ||
                                $existingTo != $effectiveTo
                            );

                            if ($hasChanges) {
                                $hasRevisionChanges = true;
                                // Soft delete the old record
                                $existing->delete();
                                
                                // Insert a new record instead of updating
                                $personnel->salaryStructures()->create([
                                    'salary_component_id' => $structComponentId,
                                    'amount' => $structAmount,
                                    'effective_from' => $effectiveFrom,
                                    'effective_to' => $effectiveTo,
                                ]);
                            }
                        }
                    } else {
                        $hasRevisionChanges = true;
                        // New allocation: insert it
                        $personnel->salaryStructures()->create([
                            'salary_component_id' => $structComponentId,
                            'amount' => $structAmount,
                            'effective_from' => $effectiveFrom,
                            'effective_to' => $effectiveTo,
                        ]);
                    }
                }

                // 3. Create Salary Revision if changes occurred
                if ($hasRevisionChanges) {
                    $newStructures = $personnel->salaryStructures()->get();
                    $newStructureJson = $newStructures->map(function($s) {
                        return [
                            'salary_component_id' => $s->salary_component_id,
                            'amount' => number_format((float)$s->amount, 2, '.', ''),
                            'effective_from' => $s->effective_from ? $s->effective_from->format('Y-m-d') : null,
                            'effective_to' => $s->effective_to ? $s->effective_to->format('Y-m-d') : null,
                        ];
                    })->values()->toArray();

                    \App\Models\SalaryRevision::create([
                        'personnel_id' => $personnel->id,
                        'approved_by' => auth()->id(),
                        'old_structure' => $oldStructureJson,
                        'new_structure' => $newStructureJson,
                        'reason' => 'Salary structures updated in Personnel profile.',
                        'revision_date' => now()->toDateString(),
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Personnel record updated successfully.');
    }

    public function destroy(Personnel $personnel)
    {
        $this->authorizeModule('delete');
        
        // $personnel->deleted_by = auth()->id();
        // $personnel->save();
        $personnel->delete();

        return redirect()->back()->with('success', 'Personnel record deleted successfully.');
    }
}