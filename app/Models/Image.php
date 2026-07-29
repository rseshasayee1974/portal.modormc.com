<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Image extends Model
{
        use HasFactory, SoftDeletes;

    protected $table = 'mm_images';

    protected $fillable = [
        'category',
        'ref_no',
        'alt_txt',
        'image_path',
        'image_name',
        'plant_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        return $this->image_path ? \Illuminate\Support\Facades\Storage::url($this->image_path) : null;
    }

    /**
     * Get label mapping for the model attributes.
     */
    public static function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'category' => 'Category',
            'ref_no' => 'Ref No',
            'alt_txt' => 'Alt Txt',
            'image_path' => 'Image Path',
            'image_name' => 'Image Name',
            'created' => 'Created',
            'created_by' => 'Created By',
            'plant_id' => 'Pant',
        ];
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
