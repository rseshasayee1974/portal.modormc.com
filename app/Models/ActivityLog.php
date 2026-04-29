<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ActivityLog
 * 
 * @property int $id
 * @property string|null $log_name
 * @property string $description
 * @property int|null $subject_id
 * @property string|null $subject_type
 * @property string|null $event
 * @property int|null $causer_id
 * @property string|null $causer_type
 * @property array|null $properties
 * @property string|null $batch_uuid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class ActivityLog extends Model
{
	use HasFactory;

	protected $table = 'mm_activity_log';

	protected $casts = [
		'subject_id' => 'int',
		'causer_id' => 'int',
		'properties' => 'json'
	];

	protected $fillable = [
		'log_name',
		'description',
		'subject_id',
		'subject_type',
		'event',
		'causer_id',
		'causer_type',
		'properties',
		'batch_uuid'
	];
}
