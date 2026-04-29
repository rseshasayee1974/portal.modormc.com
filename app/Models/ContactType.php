<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ContactType
 * 
 * @property int $id
 * @property string $type
 *
 * @package App\Models
 */
class ContactType extends Model
{
	use HasFactory;

	protected $table = 'mm_contact_types';
	public $timestamps = false;

	protected $fillable = [
		'type'
	];
}
