<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TermsCondition extends Model
{
    use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_terms_condition';

    protected $fillable = [
        'order_type',
        'terms_condition',
        'plant_id',
        'status',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
    ];
    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function setTermsConditionAttribute($value)
    {
        $this->attributes['terms_condition'] = self::sanitizeContent($value);
    }

    public static function sanitizeContent(?string $content): ?string
    {
        if ($content === null) return null;

        $trimmed = trim($content);
        if ($trimmed === '') return '';

        // 1. Remove HTML comments
        $trimmed = preg_replace('/<!--[\s\S]*?-->/', '', $trimmed);

        // 2. Remove Word/Docs pasted style and class attributes that introduce unwanted spacing
        $trimmed = preg_replace('/\sstyle="[^"]*"/i', '', $trimmed);
        $trimmed = preg_replace('/\sclass="[^"]*"/i', '', $trimmed);

        // 3. Remove leading & trailing empty paragraph/div tags (<p><br></p>, <p>&nbsp;</p>, <p></p>)
        $trimmed = preg_replace('/^(<p[^>]*>(\s|&nbsp;|<br\s*\/?>)*<\/p>\s*)+/i', '', $trimmed);
        $trimmed = preg_replace('/(<p[^>]*>(\s|&nbsp;|<br\s*\/?>)*<\/p>\s*)+$/i', '', $trimmed);

        // 4. Collapse multiple empty paragraphs into at most one
        $trimmed = preg_replace('/(<p[^>]*>(\s|&nbsp;|<br\s*\/?>)*<\/p>\s*){2,}/i', '<p><br></p>', $trimmed);

        // 5. Replace 3 or more <br> tags with max two
        $trimmed = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $trimmed);

        // 6. Trim whitespace directly preceding or following tags (preserving &nbsp;)
        $trimmed = preg_replace('/>[ \t]+/i', '>', $trimmed);
        $trimmed = preg_replace('/[ \t]+</i', '<', $trimmed);

        return trim($trimmed);
    }

    public function deletor()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
