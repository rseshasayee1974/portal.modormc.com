<?php

namespace App\Models\QC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Plant;
use App\Models\User;

class QcStandard extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_qc_standards';

    protected $fillable = [
        'plant_id',
        'code',
        'name',
        'organization',
        'edition_year',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function configs()
    {
        return $this->hasMany(QcProductGradeConfig::class, 'standard_id');
    }
}
