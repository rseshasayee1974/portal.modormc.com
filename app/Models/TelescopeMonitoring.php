<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TelescopeMonitoring
 * 
 * @property string $tag
 *
 * @package App\Models
 */
class TelescopeMonitoring extends Model
{
	use HasFactory;

	protected $table = 'mm_telescope_monitoring';
	protected $primaryKey = 'tag';
	public $incrementing = false;
	public $timestamps = false;
}
