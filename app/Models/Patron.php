<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;

class Patron extends Model
{
    use HasFactory, SoftDeletes, AuditFields;
    
    protected $table = 'mm_patrons';
    
    protected static function booted()
    {
        static::creating(function ($patron) {
            if (!$patron->code) {
                $patron->code = self::generateCode($patron->plant_id, $patron->patron_type);
            }
        });
    }

    // ──────────────────────────────────────────────────────────────
    // Dropdown / Lookup Scopes
    // ──────────────────────────────────────────────────────────────

    /**
     * Scope: active patrons for a given plant (entity_id + plant_id + not deleted).
     * SoftDeletes already excludes deleted_at IS NOT NULL rows automatically.
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
     * Scope: filter by one or more patron types (JSON column).
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string|array  $types  e.g. 'Vendor', ['Vendor','Transporter']
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $types)
    {
        $types = (array) $types;

        return $query->where(function ($q) use ($types) {
            foreach ($types as $type) {
                $q->whereJsonContains('patron_type', $type);
            }
        });
    }

    /**
     * Scope: exclude a specific patron by id (edit/update scenarios).
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  int  $id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeId($query, int $id)
    {
        return $query->where('mm_patrons.id', '!=', $id);
    }

    protected $appends = [
        'name',
    ];

    protected $fillable = [
        'plant_id',
        'code',
        'patron_type',
        'legal_name',
        'ledger_id',
        'operational_status',
        'pan_no',
        'gstin',
        'status',
        'displayed',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'displayed' => 'boolean',
        'patron_type' => 'array',
    ];

    public function getNameAttribute($value): string
    {
        return $value ?? (string) $this->legal_name;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['legal_name'] = $value;
    }

    /**
     * Get the entity associated with the patron.
     */
    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    /**
     * Get the ledger associated with the patron.
     */
    public function ledger()
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }

    /**
     * Get the user who created the patron.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // modifier relation provided by AuditFields trait linked to updated_by


    /**
     * Get the contacts for the patron.
     */
    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Get the bank accounts for the patron.
     */
    public function bankAccounts()
    {
        return $this->hasMany(PatronBankAccount::class);
    }

    /**
     * Get the personnels for the patron.
     */
    public function personnels()
    {
        return $this->belongsToMany(Personnel::class, 'mm_personnel_patron_rels', 'patron_id', 'employee_id');
    }

    public function addresses()
    {
        return $this->morphToMany(Address::class, 'addressable', 'mm_address_relation');
    }

    public static function generateCode($plantId, $patronTypes)
    {
        $types = (array) $patronTypes;
        $prefix = 'P';
        
        if (in_array('Customer', $types)) $prefix = 'C';
        elseif (in_array('Vendor', $types)) $prefix = 'V';
        elseif (in_array('Transporter', $types)) $prefix = 'T';

        $lastPatron = self::where('plant_id', $plantId)
            ->where('code', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $nextId = 1;
        if ($lastPatron && preg_match('/\d+$/', $lastPatron->code, $matches)) {
            $nextId = intval($matches[0]) + 1;
        }

        return $prefix . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }
}
