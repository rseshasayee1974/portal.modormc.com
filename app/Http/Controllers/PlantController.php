<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use App\Http\Requests\StorePlantRequest;
use App\Http\Requests\UpdatePlantRequest;
use App\Models\Entity;
use App\Models\AddressType;
use App\Models\ContactType;
use App\Models\StateCode;
use App\Models\Address;
use App\Models\Contact;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlantController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->hasRole('Saas Owner') || $user->hasRole('Platform Admin') || $user->hasRole('Super Admin')) {
            $allowedEntityIds = Entity::pluck('id')->toArray();
            
        } else {
            $allowedEntityIds = $user->entityUsers()->pluck('entity_id')->toArray();
            
        }
// dd($allowedEntityIds);
        $query = Plant::query()
            ->whereIn('entity_id', $allowedEntityIds)
            ->with(['addresses.addressType', 'addresses.state', 'contacts.contactType', 'entity']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            });
        }

        $sortField = $request->input('sort_field', 'id');
        $sortDirection = $request->input('sort_direction', 'desc');
        
        $plants = $query->orderBy($sortField, $sortDirection)->paginate(10)->withQueryString();

        $entities = Entity::getAllowedEntities($allowedEntityIds);

        return Inertia::render('Plants/Index', [
            'plants' => $plants,
            'filters' => $request->only(['search', 'sort_field', 'sort_direction']),
            'entities' => $entities,
            'addressTypes' => AddressType::all(['id', 'type']),
            'contactTypes' => ContactType::all(['id', 'type']),
            'states' => StateCode::all(['id', 'state_name']),
        ]);
    }

    public function store(StorePlantRequest $request)
    {
        $validated = $request->validated();
        
        return DB::transaction(function () use ($validated) {
            $plant = Plant::create($validated);

            // Handle Address
            if (!empty($validated['address']['line_1'])) {
                $addressData = $validated['address'];
                $addressData['plant_id'] = $plant->id;
                $addressData['is_primary'] = true;
                
                $address = Address::create($addressData);
                $plant->addresses()->attach($address->id);
            }

            // Handle Contact
            if (!empty($validated['contact']['name'])) {
                $contactData = $validated['contact'];
                $contactData['plant_id'] = $plant->id;
                $contactData['is_primary'] = true;
                
                $contact = Contact::create($contactData);
                $plant->contacts()->attach($contact->id);
            }

            return redirect()->back()->with('success', 'Plant created successfully.');
        });
    }

    public function update(UpdatePlantRequest $request, Plant $plant)
    {
        $validated = $request->validated();
        $user = Auth::user();
        
        return DB::transaction(function () use ($validated, $plant, $user) {
            $updatableFields = $validated;
            if (!$user->hasRole('Saas Owner')) {
                unset($updatableFields['code'], $updatableFields['name']);
            }
            $plant->update($updatableFields);

            // Handle Address
            if (!empty($validated['address']['line_1'])) {
                $addressData = $validated['address'];
                $addressData['plant_id'] = $plant->id;
                $address = $plant->addresses()->first();
                
                if ($address) {
                    $address->update($addressData);
                } else {
                    $addressData['is_primary'] = true;
                    $address = Address::create($addressData);
                    $plant->addresses()->attach($address->id);
                }
            } elseif (empty($validated['address']['line_1']) && $plant->addresses()->exists()) {
                // If line_1 was cleared, optionally we can detach/delete
                $address = $plant->addresses()->first();
                $plant->addresses()->detach($address->id);
                $address->delete();
            }

            // Handle Contact
            if (!empty($validated['contact']['name'])) {
                $contactData = $validated['contact'];
                $contactData['plant_id'] = $plant->id;
                $contact = $plant->contacts()->first();
                
                if ($contact) {
                    $contact->update($contactData);
                } else {
                    $contactData['is_primary'] = true;
                    $contact = Contact::create($contactData);
                    $plant->contacts()->attach($contact->id);
                }
            } elseif (empty($validated['contact']['name']) && $plant->contacts()->exists()) {
                // If name was cleared, optionally detach/delete
                $contact = $plant->contacts()->first();
                $plant->contacts()->detach($contact->id);
                $contact->delete();
            }

            return redirect()->back()->with('success', 'Plant updated successfully.');
        });
    }

    public function destroy(Plant $plant)
    {
        $plant->delete();
        return redirect()->back()->with('success', 'Plant deleted successfully.');
    }

    /**
     * Get plants for a specific entity (AJAX).
     */
    public function getByEntity(Request $request)
    {
        $entityId = $request->input('entity_id');
        if (!$entityId) return response()->json([]);

        $plants = Plant::where('entity_id', $entityId)
            ->where('is_active', 1)
            ->get(['id as value', 'name as label']);

        return response()->json($plants);
    }
}
