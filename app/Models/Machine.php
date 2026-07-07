<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use App\Traits\PlantScoping;

class Machine extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping;

    protected $table = 'mm_machines';

    // ──────────────────────────────────────────────────────────────
    // Dropdown / Lookup Scopes
    // ──────────────────────────────────────────────────────────────

    /**
     * Scope: active machines for a plant (entity_id + plant_id + not deleted).
     * SoftDeletes handles deleted_at IS NULL automatically.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  int|array  $plantId
     * @param  int|null   $entityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPlant($query, $plantId, ?int $entityId = null)
    {
       
            $query->where('plant_id', $plantId);
      

        if ($entityId !== null) {
            $query->where('entity_id', $entityId);
        }

        return $query;
    }

    /**
     * Scope: filter by vehicle_type (string or array).
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string|array  $types  e.g. 'Truck' or ['Truck','JCB']
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfVehicleType($query, $types)
    {
       
          return  $query->where('vehicle_type', $types);
           
    }

    /**
     * Scope: exclude a specific machine by id (edit/update scenarios).
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  int  $id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeId($query, int $id)
    {
        return $query->where('mm_machines.id', '!=', $id);
    }

    protected $fillable = [
        'registration',
        'vehicle_model',
        'make_year',
        'engine_no',
        'concrete_pump',
        'chassis_no',
        'vehicle_type',
        'capacity',
        'is_active',
        'owner_id',
        'plant_id',
        'entity_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function owner()
    {
        return $this->belongsTo(Patron::class, 'owner_id');
    }

     public function machineType()
    {
        return $this->belongsTo(MachineType::class, 'vehicle_type', 'name');
    }

    public function documents()
    {
        return $this->hasMany(MachineDocument::class, 'machine_id');
    }

    public function loans()
    {
        return $this->hasMany(MachineLoan::class, 'machine_id');
    }

    public function gpsDevice()
    {
        return $this->hasOne(GpsDevice::class, 'machine_id');
    }

    public function latestPosition()
    {
        return $this->hasOne(GpsLatestPosition::class, 'machine_id');
    }

    /**
     * Sync documents and loans from validated request data.
     */
    public function syncFleetRelations(array $data)
    {
        // 1. Sync Documents
        if (isset($data['documents'])) {
            $docIds = collect($data['documents'])->pluck('id')->filter()->toArray();
            $this->documents()->whereNotIn('id', $docIds)->delete();

            foreach ($data['documents'] as $doc) {
                // Sanitize ISO-8601 dates to YYYY-MM-DD
                if (!empty($doc['issue_date']) && is_string($doc['issue_date']) && str_contains($doc['issue_date'], 'T')) {
                    $doc['issue_date'] = substr($doc['issue_date'], 0, 10);
                }
                if (!empty($doc['expiry_date']) && is_string($doc['expiry_date']) && str_contains($doc['expiry_date'], 'T')) {
                    $doc['expiry_date'] = substr($doc['expiry_date'], 0, 10);
                }

                if (isset($doc['id'])) {
                    MachineDocument::where('id', $doc['id'])->update($doc);
                } else {
                    $this->documents()->create($doc);
                }
            }
        }

        // 2. Sync Loans
        if (isset($data['loans'])) {
            $loanIds = collect($data['loans'])->pluck('id')->filter()->toArray();
            $this->loans()->whereNotIn('id', $loanIds)->delete();

            foreach ($data['loans'] as $loan) {
                // Sanitize ISO-8601 dates to YYYY-MM-DD
                if (!empty($loan['start_date']) && is_string($loan['start_date']) && str_contains($loan['start_date'], 'T')) {
                    $loan['start_date'] = substr($loan['start_date'], 0, 10);
                }
                if (!empty($loan['end_date']) && is_string($loan['end_date']) && str_contains($loan['end_date'], 'T')) {
                    $loan['end_date'] = substr($loan['end_date'], 0, 10);
                }

                if (isset($loan['id'])) {
                    MachineLoan::where('id', $loan['id'])->update($loan);
                } else {
                    $this->loans()->create($loan);
                }
            }
        }
    }
}