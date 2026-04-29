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
 * Class EntityTax
 * 
 * @property int $id
 * @property int $entity_id
 * @property string $tax_type
 * @property string $tax_number
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
class EntityTax extends Model
{
	use HasFactory;

	use SoftDeletes;
	protected $table = 'mm_entity_taxes';

	protected $casts = [
		'entity_id' => 'int',
		'country_id' => 'int',
		'state_id' => 'int',
		'is_primary' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'entity_id',
		'tax_type',
		'tax_number',
		'country_id',
		'state_id',
		'is_primary',
		'created_by',
		'updated_by',
		'deleted_by'
	];
}
