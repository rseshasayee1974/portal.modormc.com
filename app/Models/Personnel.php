<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;

class Personnel extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_personnels';

    // ──────────────────────────────────────────────────────────────
    // Dropdown / Lookup Scopes
    // ──────────────────────────────────────────────────────────────

    /**
     * Scope: active personnel for a plant (entity_id + plant_id + not deleted).
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
     * Scope: exclude a specific person by id (edit/update scenarios).
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  int  $id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeId($query, int $id)
    {
        return $query->where('mm_personnels.id', '!=', $id);
    }

    protected $fillable = [
        'entity_id',
        'plant_id',
        'first_name',
        'last_name',
        'employee_type',
        'gender',
        'date_of_birth',
        'joining_date',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function contacts()
    {
        return $this->hasMany(PersonnelContact::class, 'employee_id');
    }

    public function patrons()
    {
        return $this->belongsToMany(Patron::class, 'mm_personnel_patron_rels', 'employee_id', 'patron_id');
    }
}
