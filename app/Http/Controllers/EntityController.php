<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\EntityType;
use App\Models\AddressType;
use App\Models\ContactType;
use App\Models\BankAccountType;
use App\Models\Country;
use App\Models\StateCode;
use App\Models\EntityAddress;
use App\Models\EntityContact;
use App\Models\EntityBankAccount;
use App\Models\EntityTax;
use App\Http\Requests\StoreEntityRequest;
use App\Http\Requests\UpdateEntityRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Concerns\AuthorizesModule;

class EntityController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'entities';
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('Entities/Index', [
            'entities' => Entity::all(),
            'entityTypes' => EntityType::all(),
            'addressTypes' => AddressType::all(),
            'contactTypes' => ContactType::all(),
            'bankAccountTypes' => BankAccountType::all(),
            'countries' => Country::all(['id', 'country_name']),
            'stateCodes' => StateCode::all(['id', 'state_name', 'state_code', 'country_id'])
        ]);
    }

    /**
     * Display the specified resource relationships dynamically via AJAX.
     */
    public function show(Entity $entity)
    {
        $this->authorizeModule('show');
        return response()->json([
            'entity' => $entity->load(['addresses', 'contacts', 'bankAccounts', 'taxes'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEntityRequest $request)
    {
        $this->authorizeModule('create');
        try {
            $entity = Entity::saveWithRelations($request->validated());
            $entity->load(['addresses', 'contacts', 'bankAccounts', 'taxes']);

            if ($request->wantsJson()) {
                return response()->json([
                    'entity' => $entity,
                    'message' => 'Entity created successfully.'
                ]);
            }

            return redirect()->route('entities.index')->with('success', 'Entity created successfully.');
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create entity. ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEntityRequest $request, Entity $entity)
    {
        try {
            $updatedEntity = $entity->updateWithRelations($request->validated());
            $updatedEntity->load(['addresses', 'contacts', 'bankAccounts', 'taxes']);

            if ($request->wantsJson()) {
                return response()->json([
                    'entity' => $updatedEntity,
                    'message' => 'Entity updated successfully.'
                ]);
            }

            return redirect()->route('entities.index')->with('success', 'Entity updated successfully.');
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update entity. ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Entity $entity)
    {
        DB::beginTransaction();
        try {
            // Because relationships cascaded might not exist on DB level soft delete,
            // we manually trigger deletes.
            $entity->addresses()->delete();
            $entity->contacts()->delete();
            $entity->bankAccounts()->delete();
            $entity->taxes()->delete();

            // Destroy the physical file off the partition to prevent silent data bloat leaks
            if ($entity->logo_file) {
                Storage::disk('public')->delete($entity->logo_file);
            }

            $entity->delete();

            DB::commit();

            if (request()->wantsJson()) {
                return response()->json([
                    'message' => 'Entity deleted successfully.'
                ]);
            }

            return redirect()->route('entities.index')->with('success', 'Entity deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete entity. ' . $e->getMessage()], 500);
        }
    }

    public function destroyAddress(Entity $entity, $address)
    {
        $entity->addresses()->where('id', $address)->delete();
        return response()->json(['message' => 'Address deleted successfully.']);
    }

    public function destroyContact(Entity $entity, $contact)
    {
        $entity->contacts()->where('id', $contact)->delete();
        return response()->json(['message' => 'Contact deleted successfully.']);
    }

    public function destroyBankAccount(Entity $entity, $bankAccount)
    {
        $entity->bankAccounts()->where('id', $bankAccount)->delete();
        return response()->json(['message' => 'Bank account deleted successfully.']);
    }

    public function destroyTax(Entity $entity, $tax)
    {
        $entity->taxes()->where('id', $tax)->delete();
        return response()->json(['message' => 'Tax deleted successfully.']);
    }
}
