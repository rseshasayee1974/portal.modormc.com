<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TermsCondition extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_terms_condition';

    protected $fillable = [
        'order_type',
        'terms_condition',
        'entity_id',
        'status',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletor()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
