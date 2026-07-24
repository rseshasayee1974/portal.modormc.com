<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class SalaryRevision extends Model
{
        use HasFactory, SoftDeletes;

    protected $table = 'mm_salary_revisions';

    protected $fillable = [
        'personnel_id',
        'approved_by',
        'old_structure',
        'new_structure',
        'reason',
        'revision_date',
    ];

    protected $casts = [
        'old_structure' => 'json',
        'new_structure' => 'json',
        'revision_date' => 'date',
    ];

    public function personnel()
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
