<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Country
 * 
 * @property int $id
 * @property string $country_name
 * @property string $country_code
 * @property int $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class Country extends Model
{
	use SoftDeletes, HasFactory, TracksModelChanges;

	protected $table = 'mm_countries';

	protected $casts = [
		'is_active' => 'int'
	];

	protected $fillable = [
		'country_name',
		'country_code',
		'is_active',
		'deleted_by',
	];
}
