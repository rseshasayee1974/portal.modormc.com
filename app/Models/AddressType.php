<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AddressType
 * 
 * @property int $id
 * @property string $type
 *
 * @package App\Models
 */
class AddressType extends Model
{
	use HasFactory;

	protected $table = 'mm_address_types';
	public $timestamps = false;

	protected $fillable = [
		'type'
	];
}
