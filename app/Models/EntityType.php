<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EntityType
 * 
 * @property int $id
 * @property string $type
 *
 * @package App\Models
 */
class EntityType extends Model
{
	use HasFactory;

	protected $table = 'mm_entity_types';
	public $timestamps = false;

	protected $fillable = [
		'type'
	];
}
