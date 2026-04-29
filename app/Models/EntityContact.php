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
 * Class EntityContact
 * 
 * @property int $id
 * @property int $entity_id
 * @property int $contact_type
 * @property string $contact_person
 * @property string|null $email
 * @property string|null $mobile
 * @property string|null $alt_mobile
 * @property string|null $landline
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
class EntityContact extends Model
{
	use HasFactory;

	use SoftDeletes;
	protected $table = 'mm_entity_contacts';

	protected $casts = [
		'entity_id' => 'int',
		'contact_type' => 'int',
		'is_primary' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'entity_id',
		'contact_type',
		'contact_person',
		'email',
		'mobile',
		'alt_email',
		'alt_mobile',
		'landline',
		'is_primary',
		'created_by',
		'updated_by',
		'deleted_by'
	];
}
