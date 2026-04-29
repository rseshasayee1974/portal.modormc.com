<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Plan
 * 
 * @property int $id
 * @property string $plan_type
 * @property float $price_monthly
 * @property string|null $monthly_plan_description
 * @property float $price_yearly
 * @property string|null $yearly_plan_description
 * @property int $max_users
 * @property array|null $features_json
 * @property int $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class Plan extends Model
{
	use HasFactory, SoftDeletes;

	protected $table = 'mm_plans';

	protected $casts = [
		'price_monthly' => 'float',
		'price_yearly' => 'float',
		'max_users' => 'int',
		'features_json' => 'json',
		'is_active' => 'int'
	];

	protected $fillable = [
		'plan_type',
		'price_monthly',
		'monthly_plan_description',
		'price_yearly',
		'yearly_plan_description',
		'max_users',
		'features_json',
		'is_active'
	];

	public function planFeatures(): HasMany
	{
		return $this->hasMany(PlanFeature::class, 'plan_id');
	}

	public function features(): BelongsToMany
	{
		return $this->belongsToMany(Feature::class, 'mm_plan_features', 'plan_id', 'feature_id')
			->withPivot(['id', 'value'])
			->withTimestamps();
	}

	public function subscriptions(): HasMany
	{
		return $this->hasMany(EntitySubscription::class, 'plan_id');
	}
}
