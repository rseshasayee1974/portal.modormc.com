<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ModelHasPermission
 * 
 * @property int $permission_id
 * @property string $model_type
 * @property int $model_id
 *
 * @package App\Models
 */
class ModelHasPermission extends Model
{
	use HasFactory;

	protected $table = 'mm_model_has_permissions';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'permission_id' => 'int',
		'model_id' => 'int'
	];
}
