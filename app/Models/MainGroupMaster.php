<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MainGroupMaster
 *
 * @property int $id
 * @property string $group_name
 * @property string $group_code
 * @property string|null $description
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @package App\Models
 */
class MainGroupMaster extends Model
{
    use HasFactory;

    protected $table = 'mm_main_group_master';

    protected $casts = [
        'status' => 'boolean',
    ];

    protected $fillable = [
        'group_name',
        'group_code',
        'description',
        'status',
    ];
}
