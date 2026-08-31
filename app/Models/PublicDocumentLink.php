<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicDocumentLink extends Model
{
    use SoftDeletes, HasFactory, TracksModelChanges;

    protected $table = 'mm_public_document_links';

    protected $fillable = [
        'document_type',
        'document_id',
        'token',
        'expires_at',
        'is_active',
        'created_by',
        'plant_id',
        'document_params',
        'deleted_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'document_params' => 'array',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'document_id');
    }
}
