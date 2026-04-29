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
