<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CustomSetting extends Model
{
    use HasFactory;

    protected $table = 'mm_custom_settings';

    protected $fillable = [
        'plant_id',
        'mm_module_id',
        'mm_module_name',
        'settings',
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
        return $this->attributes['mm_module_name'] ?? null;
    }

    public function setModuleNameAttribute($value): void
    {
        $this->attributes['mm_module_name'] = $value;
    }

    public function getModuleIdAttribute()
    {
        return $this->attributes['mm_module_id'] ?? null;
    }

    public function setModuleIdAttribute($value): void
    {
        $this->attributes['mm_module_id'] = $value;
    }

    public static function getForModule($plantId, $moduleName)
    {
        $moduleNameColumn = Schema::hasColumn('mm_custom_settings', 'mm_module_name')
            ? 'mm_module_name'
            : 'module_name';

        return self::where('plant_id', $plantId)
            ->where($moduleNameColumn, $moduleName)
            ->first()?->settings ?? [];
    }
}
