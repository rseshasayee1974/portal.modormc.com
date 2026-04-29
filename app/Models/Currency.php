<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Currency
 * 
 * @property int $id
 * @property string $currency_name
 * @property string $currency_code
 *
 * @package App\Models
 */
class Currency extends Model
{
	use HasFactory;

	protected $table = 'mm_currencies';
	public $timestamps = false;

	protected $fillable = [
		'currency_name',
		'currency_code'
	];
}
