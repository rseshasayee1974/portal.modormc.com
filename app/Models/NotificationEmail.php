<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class NotificationEmail extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'notification_emails';

    protected $fillable = [
        'plant_id',
        'type',
        'role_name',
        'email',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status' => 'integer',
        'id' => 'integer',
        'plant_id' => 'integer',
    ];

    /**
     * Relationship: The plant this configuration belongs to.
     */
    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
