<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;

/**
 * Class EntityUser
 * 
 * @property int $id
 * @property int $user_id
 * @property int $entity_id
 * @property int|null $plant_id
 * @property int $role_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @package App\Models
 */
class EntityUser extends Model
{
	use HasFactory, SoftDeletes, AuditFields;
	protected $table = 'mm_entity_users';

	protected $casts = [
		'user_id' => 'int',
		'entity_id' => 'int',
		'plant_id' => 'int',
		'role_id' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'user_id',
		'entity_id',
		'plant_id',
		'role_id',
		'created_by',
		'updated_by',
		'deleted_by'
	];

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($entityUser) {
            self::clearContextCache($entityUser->user_id);
        });

        static::deleted(function ($entityUser) {
            self::clearContextCache($entityUser->user_id);
        });
    }

    public static function getContextVersion(int $userId): string
    {
        return \Illuminate\Support\Facades\Cache::rememberForever("user_context_version_{$userId}", function () {
            return (string) now()->timestamp;
        });
    }

    public static function clearContextCache(int $userId): void
    {
        \Illuminate\Support\Facades\Cache::forget("user_context_version_{$userId}");
    }

    public static function getGlobalRolesVersion(): string
    {
        return \Illuminate\Support\Facades\Cache::rememberForever("global_roles_version", function () {
            return (string) now()->timestamp;
        });
    }

    public static function clearGlobalRolesCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget("global_roles_version");
    }

	public function entity()
	{
		return $this->belongsTo(Entity::class, 'entity_id');
	}

	public function plant()
	{
		return $this->belongsTo(Plant::class, 'plant_id');
	}

	public function role()
	{
		return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'role_id');
	}
}
