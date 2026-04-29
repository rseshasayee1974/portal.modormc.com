<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;

class ProductUnit extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_product_units';

    // ──────────────────────────────────────────────────────────────
    // Dropdown / Lookup Scopes
    // ──────────────────────────────────────────────────────────────

    /**
     * Scope: active units for dropdown (not deleted + optional unit_type).
     * SoftDeletes handles deleted_at IS NULL automatically (entity_id not
     * applicable for global lookup table).
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string|null  $unitType  e.g. 'purchase', 'sales'
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForDropdown($query, string $unitType = null)
    {
        if ($unitType !== null) {
            $query->where('unit_type', $unitType);
        }

        return $query;
    }

    /**
     * Scope: exclude a specific unit by id (edit/update scenarios).
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  int  $id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeId($query, int $id)
    {
        return $query->where('mm_product_units.id', '!=', $id);
    }

    protected $fillable = [
        'unit_type',
        'unit_name',
        'unit_code',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
