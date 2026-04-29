<?php

namespace App\Models;

use App\Traits\AuditFields;
use Illuminate\Database\Eloquent\Model;

class WorkOrderLog extends Model
{
    use AuditFields;
    protected $table = 'mm_work_order_logs';
    public $timestamps = false;

    protected $fillable = [
        'work_order_id',
        'status',
        'remarks',
        'changed_by',
        'changed_at',
        'created_by',
        'created',
        'modified',
        'updated_by',
        'deleted_by'
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
