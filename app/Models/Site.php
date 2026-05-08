<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;
use App\Traits\ProtectsSystemItems;

class Site extends Model
{
    /** @use HasFactory<\Database\Factories\SiteFactory> */
    use HasFactory, SoftDeletes, AuditFields, ProtectsSystemItems;

    const CREATED_AT = 'created_at';

    // ──────────────────────────────────────────────────────────────
    // Dropdown / Lookup Scopes
    // ──────────────────────────────────────────────────────────────

    /**
     * Scope: active sites for a plant (entity_id + plant_id + not deleted).
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
     * Scope: filter by site type (e.g. 'loading', 'unloading').
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string|array  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $type)
    {
        return is_array($type)
            ? $query->whereIn('type', $type)
            : $query->where('type', $type);
    }

    /**
     * Scope: exclude a specific site by id (edit/update scenarios).
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  int  $id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeId($query, int $id)
    {
        return $query->where('mm_sites.id', '!=', $id);
    }
    protected static function booted()
    {
        static::saving(function ($site) {
            // Sync is_active boolean with status string for dropdown filtering
            if ($site->status === 'InActive') {
                $site->is_active = false;
            } else {
                // Default to true for 'Active' or null status
                $site->is_active = true;
            }
        });
    }

    protected $table = 'mm_sites';
    protected $fillable = [
        'plant_id',
        'name',
        'site_address_1',
        'zipcode',
        'code',
        'type',
        'is_restricted',
        'is_reset',
        'status',   //Active, InActive, Maintenance, 
        'is_active',
        'is_system',
        'latitude',
        'longitude',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = ['is_in_use'];

    protected $casts = [
        'is_restricted' => 'boolean',
        'is_reset' => 'boolean',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function getIsInUseAttribute()
    {
        return \App\Models\WorkOrder::where('site_id', $this->id)->exists() ||
               \App\Models\Quotation::where('site_id', $this->id)->exists() ||
               \App\Models\Dispatch::where('load_site_id', $this->id)->orWhere('unload_site_id', $this->id)->exists();
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }
}
