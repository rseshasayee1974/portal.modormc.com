<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Class Permission
 * 
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class Permission extends SpatiePermission
{
	use HasFactory;

    protected $table = 'mm_permissions';

    protected $fillable = [
        'name',
        'code',
        'guard_name',
        'module',
        'description',
        'is_system',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($permission) {
            if ($permission->is_system) {
                throw new \Exception("System permissions cannot be deleted.");
            }
        });
    }
}
