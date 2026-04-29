<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

/**
 * Class StateCode
 * 
 * @property int $id
 * @property int $country_id
 * @property string $state_code
 * @property string $state_name
 *
 * @package App\Models
 */
class StateCode extends Model
{
	use HasFactory;

	protected $table = 'mm_state_codes';
	public $timestamps = false;

	protected $casts = [
		'country_id' => 'int'
	];

	protected $fillable = [
		'country_id',
		'state_code',
		'state_name'
	];
}
