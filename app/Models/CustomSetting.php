<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CustomSetting extends Model
{
    use SoftDeletes, HasFactory, TracksModelChanges;

    protected $table = 'mm_custom_settings';

    protected $fillable = [
        'plant_id',
        'module_id',
        'module_name',
        'settings',
        'deleted_by',
    ];

    /**
     * Cast settings to array
     */
    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Backward-compatible attribute aliases for legacy code.
     */
    public function getModuleNameAttribute()
    {
        return $this->attributes['module_name'] ?? null;
    }

    public function setModuleNameAttribute($value): void
    {
        $this->attributes['module_name'] = $value;
    }

    public function getModuleIdAttribute()
    {
        return $this->attributes['module_id'] ?? null;
    }

    public function setModuleIdAttribute($value): void
    {
        $this->attributes['module_id'] = $value;
    }

    public static function getForModule($plantId, $moduleName)
    {
        $moduleNameColumn = Schema::hasColumn('mm_custom_settings', 'module_name')
            ? 'module_name'
            : 'module_name';

        return self::where('plant_id', $plantId)
            ->where($moduleNameColumn, $moduleName)
            ->first()?->settings ?? [];
    }


    // with stock deduction in batch processing
    // Checked = true = Stock is deducted
    // Unchecked = false = Stock is bypassed

    // Select 'Enable' (1) = Stock is deducted
    // Select 'Disable' (0) = Stock is bypassed
}
