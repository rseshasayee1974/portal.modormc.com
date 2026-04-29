<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feature extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_features';

    protected $casts = [
        'is_active' => 'int',
    ];

    protected $fillable = [
        'name',
        'code',
        'type',
        'is_active',
    ];

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'feature_id');
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'mm_plan_features', 'feature_id', 'plan_id')
            ->withPivot(['id', 'value'])
            ->withTimestamps();
    }
}
