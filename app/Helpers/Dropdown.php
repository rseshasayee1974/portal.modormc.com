<?php

/**
 * Global Dropdown Helper Functions
 * ==================================
 * Each function produces a lightweight collection for populating form
 * dropdowns / select options.  All helpers delegate to the Eloquent query
 * scopes defined on each model, which already enforce:
 *
 *   - entity_id  (where applicable)
 *   - plant_id
 *   - deleted_at IS NULL  (SoftDeletes applied automatically)
 *
 * Additional per-model conditions:
 *   Patron      → patron_type  (v2)
 *   Product     → category_id  (v3)
 *   Tax         → tax_group, tax_type (GST/IGST only), parent_id IS NULL  (v4)
 *   Machine     → vehicle_type  (v5)
 *   Site        → type  (v6)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Usage examples
 * ─────────────────────────────────────────────────────────────────────────────
 *  PatronsDropdown($plantId)                              // all patrons
 *  PatronsDropdown($plantId, ['Vendor', 'Transporter'])   // by type
 *  PatronsDropdown($plantId, null, $excludeId)            // edit: exclude self
 *  TaxesDropdown($plantIds, 'purchase', ['GST','IGST'])   // purchase GST/IGST
 *  SitesDropdown($plantId, 'loading')                     // loading sites only
 * ─────────────────────────────────────────────────────────────────────────────
 */

use App\Models\Entity;
use App\Models\Plant;
use App\Models\Patron;
use App\Models\Machine;
use App\Models\Currency;
use App\Models\Tax;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductCategory;
use App\Models\Personnel;
use App\Models\Site;
use App\Models\Ledger;
use App\Models\ContactType;
use App\Models\AddressType;
use App\Models\BankAccountType;
use App\Models\StateCode;
use App\Models\MixDesign;
use App\Models\ExpenseType;
use App\Models\PaymentMethod;
use App\Services\PlantContextService;

// ─────────────────────────────────────────────────────────────────────────────
// Internal helper — single source of truth for the active plant ID in this file.
// Falls back to user->default_plant_id when the session is unavailable.
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('_activePlantId')) {
    function _activePlantId(): ?int
    {
        return app(PlantContextService::class)->plantId();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Entity / Plant helpers
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('EntitiesDropdown')) {
    /**
     * Active entities by IDs.
     *
     * @param  array  $allowedEntityIds
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function EntitiesDropdown(array $allowedEntityIds)
    {
        return Entity::whereIn('id', $allowedEntityIds)
            ->select('id', 'legal_name')
            ->whereNull('deleted_at')
            ->get();
    }
}

if (!function_exists('PlantsDropdown')) {
    /**
     * Plants belonging to the given entities.
     *
     * @param  array  $allowedEntityIds
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function PlantsDropdown(array $allowedEntityIds)
    {
        return Plant::whereIn('entity_id', $allowedEntityIds)
            ->select('id', 'name', 'entity_id')
            ->whereNull('deleted_at')
            ->get();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// v1 – Patron helpers
// Conditions: entity_id (optional), plant_id, deleted_at IS NULL
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('PatronsDropdown')) {
    /**
     * Active patrons for a plant, optionally filtered by patron_type.
     *
     * v2 extension: pass `$patronTypes` to filter on the patron_type JSON column.
     * Edit scenarios: pass `$excludeId` to exclude the record being edited.
     *
     * @param  int|array        $plantId
     * @param  string|array|null $patronTypes  e.g. 'Vendor' or ['Vendor','Transport']
     * @param  int|null          $excludeId    Exclude patron (edit mode)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function PatronsDropdown($patronTypes = null, $excludeId = null, $plantId = null)
    {
        if ($plantId == null) {
            $plantId = _activePlantId();
        }
        $query = Patron::where('plant_id', $plantId)
            ->select('id', 'legal_name', 'plant_id');

        if ($patronTypes !== null) {
            $query->ofType($patronTypes);
        }

        if ($excludeId !== null) {
            $query->excludeId($excludeId);
        }

        return $query->whereNull('deleted_at')->orderBy('legal_name')->get();
    }
}

if (!function_exists('VendorsDropdown')) {
    function VendorsDropdown($patronTypes = null, $excludeId = null)
    {
        return PatronsDropdown($patronTypes, $excludeId);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// v1 – Machine / Vehicle helpers
// Conditions: entity_id (optional), plant_id, deleted_at IS NULL
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('MachinesDropdown')) {
    /**
     * Active machines for a plant, optionally filtered by vehicle_type.
     *
     * v5 extension: pass `$vehicleType` to filter on vehicle_type column.
     * Edit scenarios: pass `$excludeId` to exclude the record being edited.
     *
     * @param  int|array         $plantId
     * @param  string|array|null $vehicleType  e.g. 'Truck' or ['Truck','JCB']
     * @param  int|null          $excludeId    Exclude machine (edit mode)
     * @param  int|null          $entityId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function MachinesDropdown($vehicleType = null, $excludeId = null, $entityId = null)
    {
        $query = Machine::where('plant_id', _activePlantId())
            ->where('is_active', true)
            ->select('id', 'registration', 'plant_id');

        if ($vehicleType !== null) {
            $query->where('vehicle_type', $vehicleType);
        }

        if ($excludeId !== null) {
            $query->excludeId($excludeId);
        }

        return $query->whereNull('deleted_at')->orderBy('registration')->get();
    }
}

if (!function_exists('VehiclesDropdown')) {
    function VehiclesDropdown($vehicleType = null)
    {
        return MachinesDropdown($vehicleType);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// v1 – Site helpers
// Conditions: entity_id (optional), plant_id, deleted_at IS NULL
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('SitesDropdown')) {
    /**
     * Active sites for a plant, optionally filtered by type.
     *
     * v6 extension: pass `$type` to filter on the type column.
     * Edit scenarios: pass `$excludeId` to exclude the record being edited.
     *
     * @param  int|array         $plantId
     * @param  string|array|null $type       e.g. 'loading' or 'unloading'
     * @param  int|null          $excludeId  Exclude site (edit mode)
     * @param  int|null          $entityId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function SitesDropdown($type = null, $excludeId = null)
    {
        $query = Site::where('plant_id', _activePlantId())
            ->select('id', 'name', 'code', 'plant_id');

        if ($type != null) {
            $query->where('type', $type);
        }

        if ($excludeId !== null) {
            $query->excludeId($excludeId);
        }

        return $query->whereNull('deleted_at')->where('is_active', true)->orderBy('name')->get();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// v1 – Personnel helpers
// Conditions: entity_id (optional), plant_id, deleted_at IS NULL
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('PersonnelDropdown')) {
    /**
     * Active personnel for a plant.
     *
     * Edit scenarios: pass `$excludeId` to exclude the record being edited.
     *
     * @param  int|array  $plantId
     * @param  int|null   $excludeId  Exclude personnel (edit mode)
     * @param  int|null   $entityId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function PersonnelDropdown($excludeId = null, $entityId = null)
    {
        return Personnel::where('plant_id', _activePlantId())
            ->when($excludeId, fn($q) => $q->excludeId($excludeId))
            ->whereNull('deleted_at')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'label' => trim($p->first_name . ' ' . $p->last_name),
                'first_name' => $p->first_name,
                'last_name' => $p->last_name,
                'value' => $p->id
            ]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// v3 – Product helpers
// Conditions: entity_id (optional), plant_id, deleted_at IS NULL
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('ProductsDropdown')) {
    /**
     * Active products for a plant, optionally filtered by product_type and/or category.
     *
     * v3 extension: pass `$categoryId` to filter on category_id column.
     * Edit scenarios: pass `$excludeId` to exclude the record being edited.
     *
     * @param  int|array         $plantId
     * @param  string|null       $productType  e.g. 'purchase' or 'sales'
     * @param  int|array|null    $categoryId   Category filter (v3)
     * @param  int|null          $excludeId    Exclude product (edit mode)
     * @param  int|null          $entityId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function ProductsDropdown(?string $productType = null, $categoryId = null, $excludeId = null, $entityId = null)
    {
        $query = Product::where('plant_id', _activePlantId())
            ->with('unit');

        if ($productType !== null) {
            $query->where('product_type', $productType);
        }

        if ($categoryId !== null) {
            $query->ofCategory($categoryId);
        }

        if ($excludeId !== null) {
            $query->excludeId($excludeId);
        }

        return $query->whereNull('deleted_at')->get(['id', 'title', 'code', 'unit_id', 'sales_price', 'purchase_price']);
    }
}

if (!function_exists('MixDesignsDropdown')) {
    /**
     * Active mix designs for a plant.
     */
    function MixDesignsDropdown()
    {
        return MixDesign::query()
            ->where('plant_id', _activePlantId())
            ->whereNull('deleted_at')
            ->select('id', 'design_name as title', 'design_code as code', 'rate_per_qty as rate', 'unit_id')
            ->orderBy('design_name')
            ->get();
    }
}

if (!function_exists('MixDesignsOptions')) {
    /**
     * Map mix designs to select options with extra metadata (rate, uom_id).
     */
    function MixDesignsOptions()
    {
        return collect(MixDesignsDropdown())->map(fn($d) => [
            'label' => $d->title,
            'value' => $d->id,
            'rate'  => $d->rate,
            'uom_id' => $d->unit_id,
        ]);
    }
}

if (!function_exists('ExpenseTypesDropdown')) {
    /**
     * Active expense types for a plant.
     */
    function ExpenseTypesDropdown()
    {
        return ExpenseType::where('plant_id', _activePlantId())
            ->where('status', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}

if (!function_exists('GradeDropdown')) {
    /**
     * Concrete grades (used for Mix Designs).
     */
    function GradeDropdown()
    {
        return \App\Models\ConcreteGrade::where('plant_id', _activePlantId())
            ->select('id', 'grade_name as title')
            ->whereNull('deleted_at')
            ->orderBy('grade_name')
            ->get();
    }
}

if (!function_exists('ProductCategoriesDropdown')) {
    /**
     * Active product categories for a plant.
     *
     * @param  int|array  $plantId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function ProductCategoriesDropdown()
    {
        return ProductCategory::where('plant_id', _activePlantId())
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->get(['id', 'name']);
    }
}

if (!function_exists('ProductTypesDropdown')) {
    /**
     * List of valid product types.
     *
     * @return array
     */
    function ProductTypesDropdown()
    {
        return ['Inventory', 'Service', 'Purchase', 'Sales'];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// v4 – Tax helpers
// Conditions: entity_id (optional), plant_id, deleted_at IS NULL
//             + tax_group IN ('GST','IGST'), parent_id IS NULL
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('TaxesDropdown')) {
    /**
     * Active taxes for a plant.
     *
     * @param  int|array         $plantId
     * @param  string|null       $taxType    e.g. 'purchase', 'sales'
     * @param  string|array|null $taxGroup   e.g. ['GST','IGST']
     * @param  bool              $parentOnly Return only parent_id IS NULL rows
     * @param  int|null          $excludeId  Exclude tax id
     * @param  int|null          $includeId  Specifically include this id (even if status is 0 or deleted?) - usually for edit mode consistency
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function TaxesDropdown(
        $taxType = null,
        $taxGroup = null,
        bool $parentOnly = true,
        $excludeId = null,
        $includeId = null
    ) {
        $query = Tax::where('plant_id', _activePlantId())
            ->where('status', 1)
            ->select('id', 'tax_name', 'tax_rate', 'tax_group', 'tax_type', 'status');

        if ($taxType !== null) {
            $query->where('tax_type', $taxType);
        }

        if ($taxGroup !== null) {
            if (is_array($taxGroup)) {
                $query->whereIn('tax_group', $taxGroup);
            } else {
                $query->where('tax_group', $taxGroup);
            }
        }

        if ($parentOnly) {
            $query->whereNull('parent_id');
        }

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($includeId !== null) {
            $query->orWhere('id', $includeId);
        }

        return $query->whereNull('deleted_at')->orderBy('tax_name')->get();
    }
}

 

if (!function_exists('SaleTaxesDropdown')) {
    /**
     * Active sale taxes for a plant.
     */
    function SaleTaxesDropdown()
    {
        return Tax::where('plant_id', _activePlantId())
            ->where('tax_type', 'sales')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->get(['id', 'tax_name', 'tax_rate', 'tax_group', 'tax_type']);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ProductUnit helper  (global lookup – no plant scoping needed)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('Productunit')) {
    /**
     * Active product units, optionally filtered by unit_type.
     * SoftDeletes ensures deleted records are excluded.
     *
     * @param  string|null  $unitType  e.g. 'purchase', 'sales'
     * @param  int|null     $excludeId Exclude unit (edit mode)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function Productunit(?string $unitType = null, ?int $excludeId = null)
    {
        $query = ProductUnit::forDropdown($unitType)
            ->select('id', 'unit_name', 'unit_code');

        if ($excludeId !== null) {
            $query->excludeId($excludeId);
        }

        return $query->whereNull('deleted_at')->orderBy('unit_name')->get();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Currency helper  (global lookup – no plant scoping needed)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('CurrenciesDropdown')) {
    function CurrenciesDropdown()
    {
        return Currency::all(['id', 'currency_name', 'currency_code']);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Custom / Specific helpers
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('ActivePlantsDropdown')) {
    /**
     * Active plants dropdown.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function ActivePlantsDropdown()
    {
        return Plant::where('is_active', true)->select('id', 'name')->whereNull('deleted_at')->get();
    }
}

if (!function_exists('LedgersDropdown')) {
    /**
     * All ledgers for a specific plant.
     *
     * @param  int|array  $plantId
     * @param  string|null $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function LedgersDropdown($type = null)
    {
        $query = Ledger::query()->where('plant_id', _activePlantId());
        
        if ($type) {
            $query->whereHas('accountType.account', function($q) use ($type) {
                $q->where('title', $type);
            });
        }

        return $query->select('id', 'code as name', 'title')
            ->whereNull('deleted_at')
            ->get();
    }
}

if (!function_exists('SalesLedgersDropdown')) {
    /**
     * Sales-specific ledgers for a plant.
     *
     * @param  int|array  $plantId
     * @param  string|null $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function SalesLedgersDropdown($type = null)
    {
        $query = Ledger::query()->where('plant_id', _activePlantId());
        
        if ($type) {
             $query->where('title', '=', $type);
        }

        return $query->select('id', 'code as name', 'title') 
            ->whereNull('deleted_at')
            ->get();
    }
}

if (!function_exists('DetailedPatronsDropdown')) {
    /**
     * Detailed active patrons for a plant, latest first.
     *
     * @param int|null $plantId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function DetailedPatronsDropdown()
    {
        return Patron::where('plant_id', _activePlantId())
            ->with(['plant', 'ledger', 'contacts.addresses', 'bankAccounts'])
            ->latest()
            ->whereNull('deleted_at')
            ->get();
    }
}

if (!function_exists('ContactTypesDropdown')) {
    function ContactTypesDropdown()
    {
        return ContactType::select('id', 'type as label')->whereNull('deleted_at')->get();
    }
}

if (!function_exists('AddressTypesDropdown')) {
    function AddressTypesDropdown()
    {
        return AddressType::select('id', 'type as label')->get();
    }
}

if (!function_exists('BankAccountTypesDropdown')) {
    function BankAccountTypesDropdown()
    {
        return BankAccountType::select('id', 'type as label')->get();
    }
}

if (!function_exists('StateCodesDropdown')) {
    function StateCodesDropdown()
    {
        return StateCode::whereNotNull('state_name')
            ->where('state_name', '!=', '')
            ->whereNull('deleted_at')
            ->groupBy('state_name', 'state_code')
            ->selectRaw('MIN(id) as id, state_name, state_code')
            ->orderBy('state_name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'state_name' => $item->state_name,
                    'state_code' => $item->state_code,
                    'label' => $item->state_name,
                    'value' => $item->id,
                ];
            });
    }
}

if (!function_exists('OperationalStatusesDropdown')) {
    function OperationalStatusesDropdown()
    {
        return [
            ['label' => 'Active', 'value' => 'active'],
            ['label' => 'Paused', 'value' => 'paused'],
            ['label' => 'Blocked', 'value' => 'blocked'],
            ['label' => 'Closed', 'value' => 'closed'],
        ];
    }
}

if (!function_exists('PatronTypesDropdown')) {
    function PatronTypesDropdown()
    {
        return [
            ['label' => 'Customer', 'value' => 'Customer'],
            ['label' => 'Vendor', 'value' => 'Vendor'],
            ['label' => 'Employee', 'value' => 'Employee'],
            ['label' => 'Supplier', 'value' => 'Supplier'],
            ['label' => 'Transporter', 'value' => 'Transporter'],
            ['label' => 'Other', 'value' => 'Other'],
        ];
    }
}

if (!function_exists('PurchaseOrderStatusesDropdown')) {
    /**
     * Standard statuses for Purchase Orders.
     */
    function PurchaseOrderStatusesDropdown()
    {
        return [
            ['label' => 'Draft', 'value' => 'draft'],
            ['label' => 'Approved', 'value' => 'approved'],
            ['label' => 'Billed', 'value' => 'billed'],
            ['label' => 'Cancelled', 'value' => 'cancel'],
        ];
    }
}

if (!function_exists('PaymentMethodsDropdown')) {
    /**
     * Active payment methods.
     */
    function 
    PaymentMethodsDropdown()
    {
        return PaymentMethod::where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
// ─────────────────────────────────────────────────────────────────────────────
// Utility helper for Select Options
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('toSelectOptions')) {
    /**
     * Map a collection to a format suitable for generic Select/Dropdown components.
     *
     * @param \Illuminate\Support\Collection|array $collection
     * @param string $labelField
     * @param string $valueField
     * @return \Illuminate\Support\Collection
     */
    function toSelectOptions($collection, string $labelField, string $valueField = 'id')
    {
        return collect($collection)->map(function ($item) use ($labelField, $valueField) {
            return [
                'label' => data_get($item, $labelField),
                'value' => data_get($item, $valueField),
            ];
        })->values();
    }
}