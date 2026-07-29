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
use App\Services\PlantInitializationService;

class PlantController extends Controller
{
    protected $initService;

    public function __construct(PlantInitializationService $initService)
    {
        $this->initService = $initService;
    }

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
            'states' => StateCodesDropdown(),
        ]);
    }

    public function store(StorePlantRequest $request)
    {
        $validated = $request->validated();
        
        return DB::transaction(function () use ($validated, $request) {
            $plant = Plant::create($validated);

            // Handle Logo Upload
            if ($request->hasFile('logo')) {
                $entity = Entity::find($validated['entity_id']);
                $entitySlug = \Illuminate\Support\Str::slug($entity->legal_name);
                $plantSlug = \Illuminate\Support\Str::slug($plant->name);
                
                $path = $request->file('logo')->storeAs(
                    "plants/{$entitySlug}/{$plantSlug}",
                    "logo_" . time() . "." . $request->file('logo')->getClientOriginalExtension(),
                    'public'
                );
                
                $plant->update(['logo_path' => $path]);
            }

            // Handle Seal & Signature Upload
            if ($request->hasFile('seal_sign')) {
                $entity = Entity::find($validated['entity_id']);
                $entitySlug = \Illuminate\Support\Str::slug($entity->legal_name);
                $plantSlug = \Illuminate\Support\Str::slug($plant->name);
                
                $path = $request->file('seal_sign')->storeAs(
                    "plants/{$entitySlug}/{$plantSlug}",
                    "seal_sign_" . time() . "." . $request->file('seal_sign')->getClientOriginalExtension(),
                    'public'
                );
                
                $plant->update(['seal_sign_path' => $path]);
            }

            // Handle UPI QR Code Upload
            if ($request->hasFile('upi_qr') && \Illuminate\Support\Facades\Schema::hasColumn('mm_plants', 'upi_qr_path')) {
                $entity = Entity::find($validated['entity_id']);
                $entitySlug = \Illuminate\Support\Str::slug($entity->legal_name);
                $plantSlug = \Illuminate\Support\Str::slug($plant->name);
                
                $path = $request->file('upi_qr')->storeAs(
                    "plants/{$entitySlug}/{$plantSlug}",
                    "upi_qr_" . time() . "." . $request->file('upi_qr')->getClientOriginalExtension(),
                    'public'
                );
                
                $plant->update(['upi_qr_path' => $path]);
            }

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

            // Automatically initialize plant defaults (modules, accounting, taxes, default settings)
            $this->initService->initialize($plant);

            return redirect()->back()->with('success', 'Plant created successfully.');
        });
    }

    public function update(UpdatePlantRequest $request, Plant $plant)
    {
        $validated = $request->validated();
        $user = Auth::user();
        
        return DB::transaction(function () use ($validated, $plant, $user, $request) {
            $updatableFields = $validated;
            if (!$user->hasRole('Saas Owner')) {
                unset($updatableFields['code'], $updatableFields['name']);
            }
            if (!\Illuminate\Support\Facades\Schema::hasColumn('mm_plants', 'upi_qr_path')) {
                unset($updatableFields['upi_qr_path']);
            }
            $plant->update($updatableFields);

            // Handle Logo Upload
            if ($request->hasFile('logo')) {
                // Delete old logo if exists
                if ($plant->logo_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($plant->logo_path);
                }

                $entity = Entity::find($plant->entity_id);
                $entitySlug = \Illuminate\Support\Str::slug($entity->legal_name);
                $plantSlug = \Illuminate\Support\Str::slug($plant->name);
                
                $path = $request->file('logo')->storeAs(
                    "plants/{$entitySlug}/{$plantSlug}",
                    "logo_" . time() . "." . $request->file('logo')->getClientOriginalExtension(),
                    'public'
                );
                
                $plant->update(['logo_path' => $path]);
            }

            // Handle Seal & Signature Upload
            if ($request->hasFile('seal_sign')) {
                // Delete old seal_sign if exists
                if ($plant->seal_sign_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($plant->seal_sign_path);
                }

                $entity = Entity::find($plant->entity_id);
                $entitySlug = \Illuminate\Support\Str::slug($entity->legal_name);
                $plantSlug = \Illuminate\Support\Str::slug($plant->name);
                
                $path = $request->file('seal_sign')->storeAs(
                    "plants/{$entitySlug}/{$plantSlug}",
                    "seal_sign_" . time() . "." . $request->file('seal_sign')->getClientOriginalExtension(),
                    'public'
                );
                
                $plant->update(['seal_sign_path' => $path]);
            }

            // Handle UPI QR Code Upload
            if ($request->hasFile('upi_qr') && \Illuminate\Support\Facades\Schema::hasColumn('mm_plants', 'upi_qr_path')) {
                // Delete old upi_qr if exists
                if (!empty($plant->upi_qr_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($plant->upi_qr_path);
                }

                $entity = Entity::find($plant->entity_id);
                $entitySlug = \Illuminate\Support\Str::slug($entity->legal_name);
                $plantSlug = \Illuminate\Support\Str::slug($plant->name);
                
                $path = $request->file('upi_qr')->storeAs(
                    "plants/{$entitySlug}/{$plantSlug}",
                    "upi_qr_" . time() . "." . $request->file('upi_qr')->getClientOriginalExtension(),
                    'public'
                );
                
                $plant->update(['upi_qr_path' => $path]);
            }

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

    /**
     * Initialize default masters for a plant.
     */
    public function initialize(Plant $plant)
    {
        $user = Auth::user();

        // Check permissions: saas-owner, platform-admin, or super-admin
        if (!$user->hasRole('Saas Owner') && !$user->hasRole('Platform Admin')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($plant->is_initialized) {
            return redirect()->back()->with('error', 'Plant is already initialized.');
        }

        // Validate plant email before creating user
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['email' => $plant->email_address],
            ['email' => 'required|email:rfc,dns']
        );

        if ($validator->fails()) {
            return redirect()->back()->with('error', 'Invalid plant email address: ' . ($plant->email_address ?: 'Not provided'));
        }

        $success = $this->initService->initialize($plant);

        if ($success) {
            return redirect()->back()->with('success', 'Plant initialized with default settings successfully.');
        }

        return redirect()->back()->with('error', 'Failed to initialize plant.');
    }

    /**
     * Send or resend plant admin credentials.
     */
    public function sendCredentials(Plant $plant)
    {
        if (empty($plant->email_address)) {
            return redirect()->back()->with('error', 'Plant does not have an admin email address.');
        }

        $password = \Illuminate\Support\Str::random(10);
        
        // Ensure user exists or update password
        $user = \App\Models\User::updateOrCreate(
            ['email' => $plant->email_address],
            [
                'username' => $plant->name . ' Admin',
                'password' => \Illuminate\Support\Facades\Hash::make($password),
                'is_active' => 1,
            ]
        );

        // Ensure role and entity assignment
        $role = \Spatie\Permission\Models\Role::where('name', 'Super Admin')->first();
        if ($role) $user->assignRole($role);

        \App\Models\EntityUser::updateOrCreate([
            'user_id' => $user->id,
            'entity_id' => $plant->entity_id,
            'plant_id' => $plant->id,
        ], [
            'role_id' => $role ? $role->id : 3,
            'created_by' => Auth::id() ?? 1,
        ]);

        $success = $this->initService->sendPlantCredentials($plant, $password);

        if ($success) {
            return redirect()->back()->with('success', 'Login credentials sent to ' . $plant->email_address);
        }

        return redirect()->back()->with('error', 'Failed to send credentials.');
    }
}
