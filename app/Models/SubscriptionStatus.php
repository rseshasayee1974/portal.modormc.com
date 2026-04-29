<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

/**
 * Class SubscriptionStatus
 * 
 * @property int $id
 * @property string $status_name
 *
 * @package App\Models
 */
class SubscriptionStatus extends Model
{
	use HasFactory;

	protected $table = 'mm_subscription_statuses';
	public $timestamps = false;

	protected $fillable = [
		'status_name'
	];
}
