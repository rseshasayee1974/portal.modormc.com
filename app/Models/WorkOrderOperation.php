<?php

namespace App\Models;

use App\Traits\AuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkOrderOperation extends Model
{
    use HasFactory, AuditFields, SoftDeletes;
    protected $table = 'mm_work_order_operations';
    public $timestamps = false; // Using custom audit fields

    protected $fillable = [
        'work_order_id',
        'operation_name',
        'sequence',
        'duration',
        'status',
        'started_at',
        'completed_at',
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
    
    // Logic
    public function start()
    {
        $this->update(['status' => 2, 'started_at' => now()]); // 2: In-Progress
    }

    public function complete()
    {
        $this->update(['status' => 3, 'completed_at' => now()]); // 3: Completed
    }
}
