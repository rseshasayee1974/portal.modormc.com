<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;

class Machine extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

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
    public function scopeForPlant($query, $plantId, int $entityId = null)
    {
        $query = is_array($plantId)
            ? $query->whereIn('plant_id', $plantId)
            : $query->where('plant_id', $plantId);

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
        return is_array($types)
            ? $query->whereIn('vehicle_type', $types)
            : $query->where('vehicle_type', $types);
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
        'chassis_no',
        'vehicle_type',
        'capacity',
        'owner_id',
        'plant_id',
        'entity_id',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function owner()
    {
        return $this->belongsTo(Patron::class, 'owner_id');
    }

    public function documents()
    {
        return $this->hasMany(MachineDocument::class, 'machine_id');
    }

    public function loans()
    {
        return $this->hasMany(MachineLoan::class, 'machine_id');
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
                if (isset($loan['id'])) {
                    MachineLoan::where('id', $loan['id'])->update($loan);
                } else {
                    $this->loans()->create($loan);
                }
            }
        }
    }
}
