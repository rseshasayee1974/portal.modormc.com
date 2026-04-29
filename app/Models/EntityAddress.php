<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class EntityAddress
 * 
 * @property int $id
 * @property int $entity_id
 * @property int $address_type
 * @property string $line_1
 * @property string|null $line_2
 * @property string $city
 * @property string|null $zipcode
 * @property string|null $landmark
 * @property int|null $country_id
 * @property int|null $state_id
 * @property int $is_primary
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @package App\Models
 */
class EntityAddress extends Model
{
	use HasFactory;

	use SoftDeletes;
	protected $table = 'mm_entity_addresses';

	protected $casts = [
		'entity_id' => 'int',
		'address_type' => 'int',
		'country_id' => 'int',
		'state_id' => 'int',
		'is_primary' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'entity_id',
		'address_type',
		'line_1',
		'line_2',
		'city',
		'zipcode',
		'landmark',
		'country_id',
		'state_id',
		'is_primary',
		'created_by',
		'updated_by',
		'deleted_by'
	];
}
