<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;

class Tax extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    // ──────────────────────────────────────────────────────────────
    // Dropdown / Lookup Scopes
    // ──────────────────────────────────────────────────────────────

    /**
     * Scope: active taxes for a plant (entity_id + plant_id + not deleted).
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
     * Scope: filter by tax_group values (e.g. 'GST', 'IGST').
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string|array  $groups  e.g. ['GST','IGST']
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfGroup($query, $groups)
    {
        return $query->whereIn('tax_group', (array) $groups);
    }

    /**
     * Scope: filter by tax_type (e.g. 'purchase', 'sales').
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string|array  $types
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfTaxType($query, $types)
    {
        return is_array($types)
            ? $query->whereIn('tax_type', $types)
            : $query->where('tax_type', $types);
    }

    /**
     * Scope: only top-level (parent) tax records (parent_id IS NULL).
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeParentOnly($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: exclude a specific tax by id (edit/update scenarios).
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  int  $id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeId($query, int $id)
    {
        return $query->where('mm_taxes.id', '!=', $id);
    }

    protected $appends = [
        'name',
        'rate',
    ];

    protected $table = 'mm_taxes';

    protected $fillable = [
        'plant_id',
        'entity_id',
        'tax_name',
        'tax_type',
        'tax_group',
        'tax_rate',
        'parent_id',
        'account_id',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2',
        'status' => 'integer',
        'id' => 'integer',
        'parent_id' => 'integer',
        'plant_id' => 'integer',
        'entity_id' => 'integer',
        'account_id' => 'integer',
    ];

    public function getNameAttribute($value): string
    {
        return $value ?? (string) $this->tax_name;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['tax_name'] = $value;
    }

    public function getRateAttribute($value): ?float
    {
        if ($value !== null) {
            return (float) $value;
        }

        return $this->tax_rate !== null ? (float) $this->tax_rate : null;
    }

    public function setRateAttribute($value): void
    {
        $this->attributes['tax_rate'] = $value;
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function parent()
    {
        return $this->belongsTo(Tax::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Tax::class, 'parent_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
