<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class EntitySubscription
 * 
 * @property int $id
 * @property int $entity_id
 * @property int $plan_id
 * @property int|null $scheduled_plan_id
 * @property int $subscription_status_id
 * @property string $billing_cycle
 * @property Carbon $started_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $scheduled_change_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @package App\Models
 */
class EntitySubscription extends Model
{
	use HasFactory;

	use SoftDeletes;
	protected $table = 'mm_entity_subscriptions';

	protected $casts = [
		'entity_id' => 'int',
		'plan_id' => 'int',
		'scheduled_plan_id' => 'int',
		'subscription_status_id' => 'int',
		'billing_cycle' => 'string',
		'started_at' => 'datetime',
		'expires_at' => 'datetime',
		'scheduled_change_at' => 'datetime',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'entity_id',
		'plan_id',
		'scheduled_plan_id',
		'subscription_status_id',
		'billing_cycle',
		'started_at',
		'expires_at',
		'scheduled_change_at',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	public function entity(): BelongsTo
	{
		return $this->belongsTo(Entity::class, 'entity_id');
	}

	public function plan(): BelongsTo
	{
		return $this->belongsTo(Plan::class, 'plan_id');
	}

	public function scheduledPlan(): BelongsTo
	{
		return $this->belongsTo(Plan::class, 'scheduled_plan_id');
	}

	public function status(): BelongsTo
	{
		return $this->belongsTo(SubscriptionStatus::class, 'subscription_status_id');
	}
}
