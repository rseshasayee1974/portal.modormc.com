<?php

namespace App\Http\Controllers;

use App\Models\Patron;
use App\Models\Plant;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ContactType;
use App\Models\AddressType;
use App\Models\StateCode;
use App\Models\Contact;
use App\Models\Address;
use App\Models\BankAccountType;
use App\Models\PatronBankAccount;
use App\Http\Requests\StorePatronRequest;
use App\Http\Requests\UpdatePatronRequest;
use Illuminate\Validation\Rule;

class PatronController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'mm_patrons';

    public function index()
    {
        $this->authorizeModule('menu');
        
        return Inertia::render('Patrons/Index', [
            'patrons'  => DetailedPatronsDropdown(session('active_plant_id')),
            'plants'   => ActivePlantsDropdown(),
            'ledgers'  => LedgersDropdown(),
            'contactTypes' => ContactTypesDropdown(),
            'addressTypes' => AddressTypesDropdown(),
            'bankAccountTypes' => BankAccountTypesDropdown(),
            'states' => StateCodesDropdown(),
            'operationalStatuses' => OperationalStatusesDropdown(),
            'patronTypes' => PatronTypesDropdown(),
        ]);
    }

    public function store(StorePatronRequest $request)
    {
        $this->authorizeModule('create');
        
        $validated = $request->validated();

        $plantId = session('active_plant_id');
        $plant = Plant::findOrFail($plantId);
        $entityId = $plant->entity_id;

        $validated['plant_id'] = $plantId;
        $validated['entity_id'] = $entityId;

        DB::transaction(function () use ($validated, $plantId, $entityId) {
            $patron = Patron::create($validated);

            if (!empty($validated['contact_name'])) {
                $contact = $patron->contacts()->create([
                    'plant_id'        => $plantId,
                    'contact_type_id' => $validated['contact_type_id'] ?? 1,
                    'name' => $validated['contact_name'],
                    'email' => $validated['contact_email'],
                    'mobile' => $validated['contact_mobile'],
                    'alt_mobile' => $validated['contact_alt_mobile'] ?? null,
                    'is_primary' => $validated['contact_is_primary'] ?? true,
                    'status' => $validated['contact_status'] ?? true,
                ]);

                if (!empty($validated['address_line_1'])) {
                    $contact->addresses()->create([
                        'plant_id'   => $plantId,
                        'entity_id'  => $entityId,
                        'address_type_id' => $validated['address_type_id'] ?? 1,
                        'line_1' => $validated['address_line_1'],
                        'line_2' => $validated['address_line_2'] ?? null,
                        'city' => $validated['address_city'],
                        'state_id' => $validated['address_state_id'] ?? null,
                        'state_code' => $validated['address_state_code'] ?? null,
                        'zipcode' => $validated['address_zipcode'],
                        'is_primary' => $validated['address_is_primary'] ?? true,
                        'status' => $validated['address_status'] ?? true,
                    ]);
                }
            }

            if (!empty($validated['bank_accounts'])) {
                foreach ($validated['bank_accounts'] as $acc) {
                    $patron->bankAccounts()->create([
                        'plant_id'   => $plantId,
                        // 'entity_id'  => $entityId,
                        'bank_account_type' => $acc['bank_account_type'],
                        'account_holder_name' => $acc['account_holder_name'],
                        'account_number' => $acc['account_number'],
                        'bank_name' => $acc['bank_name'],
                        'branch_name' => $acc['branch_name'] ?? null,
                        'ifsc_code' => $acc['ifsc_code'],
                        'is_primary' => $acc['is_primary'] ?? false,
                        'status' => $acc['status'] ?? true,
                    ]);
                }
            }
        });

        if ($request->wantsJson()) {
            $patron = Patron::where('legal_name', $validated['legal_name'])->first(); // Best way to get it back after tx
            return response()->json([
                'message' => 'Patron created successfully',
                'patron' => $patron
            ]);
        }

        return redirect()->back()->with('success', 'Patron created successfully with contact details.');
    }

    public function update(UpdatePatronRequest $request, Patron $patron)
    {
        $this->authorizeModule('edit');
        
        $validated = $request->validated();
        DB::transaction(function () use ($validated, $patron) {
            // Only pass patron-table columns — not contact/address/bank keys
            $patronData = array_intersect_key($validated, array_flip([
                'patron_type', 'legal_name', 'ledger_id',
                'operational_status', 'pan_no', 'gstin',
                'status', 'displayed',
            ]));
            $patron->update($patronData);

            // Handle Primary Contact
            if (!empty($validated['contact_name'])) {
                $contact = $patron->contacts()->where('is_primary', true)->where('plant_id', $patron->plant_id)->first();
                $contactData = [
                    'plant_id'        => $patron->plant_id,
                    'entity_id'       => $patron->entity_id,
                    'contact_type_id' => $validated['contact_type_id'] ?? 1,
                    'name' => $validated['contact_name'],
                    'email' => $validated['contact_email'],
                    'mobile' => $validated['contact_mobile'],
                    'alt_mobile' => $validated['contact_alt_mobile'] ?? null,
                    'is_primary' => $validated['contact_is_primary'] ?? true,
                    'status' => $validated['contact_status'] ?? true,
                ];

                if ($contact) {
                    $contact->update($contactData);
                } else {
                    $contact = $patron->contacts()->create($contactData);
                }

                // Handle Primary Address for this contact
                if (!empty($validated['address_line_1'])) {
                    $address = $contact->addresses()->where('is_primary', true)->where('plant_id', $patron->plant_id)->first();
                    $addressData = [
                        'plant_id'   => $patron->plant_id,
                        'entity_id'  => $patron->entity_id,
                        'address_type_id' => $validated['address_type_id'] ?? 1,
                        'line_1' => $validated['address_line_1'],
                        'line_2' => $validated['address_line_2'] ?? null,
                        'city' => $validated['address_city'],
                        'state_id' => $validated['address_state_id'] ?? null,
                        'state_code' => $validated['address_state_code'] ?? null,
                        'zipcode' => $validated['address_zipcode'],
                        'is_primary' => $validated['address_is_primary'] ?? true,
                        'status' => $validated['address_status'] ?? true,
                    ];

                    if ($address) {
                        $address->update($addressData);
                    } else {
                        $contact->addresses()->create($addressData);
                    }
                }
            }

            // Handle Bank Accounts
            if (isset($validated['bank_accounts'])) {
                $incomingIds = collect($validated['bank_accounts'])->pluck('id')->filter()->all();
                $patron->bankAccounts()->where('plant_id', $patron->plant_id)->whereNotIn('id', $incomingIds)->delete();

                foreach ($validated['bank_accounts'] as $acc) {
                    $accData = [
                        'plant_id'   => $patron->plant_id,
                        // 'entity_id'  => $patron->entity_id,
                        'bank_account_type' => $acc['bank_account_type'],
                        'account_holder_name' => $acc['account_holder_name'],
                        'account_number' => $acc['account_number'],
                        'bank_name' => $acc['bank_name'],
                        'branch_name' => $acc['branch_name'] ?? null,
                        'ifsc_code' => $acc['ifsc_code'],
                        'is_primary' => $acc['is_primary'] ?? false,
                        'status' => $acc['status'] ?? true,
                    ];

                    if (!empty($acc['id'])) {
                        $patron->bankAccounts()->where('id', $acc['id'])->update($accData);
                    } else {
                        $patron->bankAccounts()->create($accData);
                    }
                }
            } else {
                $patron->bankAccounts()->where('plant_id', $patron->plant_id)->delete();
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Patron updated successfully',
                'patron' => $patron
            ]);
        }

        return redirect()->back()->with('success', 'Patron updated successfully.');
    }

    public function batchStore(Request $request)
    {
        $this->authorizeModule('create');
        
        $data = $request->validate([
            'patrons' => 'required|array',
            'patrons.*.legal_name' => [
                'required', 
                'string', 
                'max:200',
                Rule::unique('mm_patrons')->where(fn($q) => $q->where('plant_id', session('active_plant_id')))
            ],
            'patrons.*.patron_type' => 'required|array',
            'patrons.*.operational_status' => 'required|string',
            'patrons.*.pan_no' => 'nullable|string',
            'patrons.*.gstin' => 'nullable|string',
            'patrons.*.status' => 'required|boolean',
            'patrons.*.displayed' => 'required|boolean',
        ]);

        $plant_id = session('active_plant_id');
        $userId = Auth::id();

        DB::transaction(function () use ($data, $plant_id, $userId) {
            foreach ($data['patrons'] as $patronData) {
                Patron::create(array_merge($patronData, [
                    'plant_id'   => $plant_id,
                ]));
            }
        });

        return redirect()->back()->with('success', count($data['patrons']) . ' patrons imported successfully.');
    }

    public function destroy(Patron $patron)
    {
        $this->authorizeModule('delete');
        $patron->delete();

        return redirect()->back()->with('success', 'Patron deleted successfully.');
    }
}
