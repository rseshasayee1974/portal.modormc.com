<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class HealthCheckResultHistoryItem
 * 
 * @property int $id
 * @property string $check_name
 * @property string $check_label
 * @property string $status
 * @property string|null $notification_message
 * @property string|null $short_summary
 * @property array $meta
 * @property Carbon $ended_at
 * @property string $batch
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class HealthCheckResultHistoryItem extends Model
{
	use HasFactory;

	protected $table = 'health_check_result_history_items';

	protected $casts = [
		'meta' => 'json',
		'ended_at' => 'datetime'
	];

	protected $fillable = [
		'check_name',
		'check_label',
		'status',
		'notification_message',
		'short_summary',
		'meta',
		'ended_at',
		'batch'
	];
}
