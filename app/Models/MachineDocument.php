<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MachineDocument extends Model
{
    use HasFactory;

    protected $table = 'mm_machine_documents';

    protected $fillable = [
        'machine_id',
        'type',
        'issue_date',
        'expiry_date',
        'amount',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
}
