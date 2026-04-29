<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class VoucherType
 *
 * @property int|null $entity_id
 * @property string $journal_name
 * @property string $short_code
 * @property bool $is_system_generated
 * @property string|null $prefix
 * @property string|null $voucher_group
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @package App\Models
 */
class VoucherType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_voucher_types';

    protected $casts = [
        'is_system_generated' => 'boolean',
        'entity_id'           => 'integer',
    ];

    protected $fillable = [
        'entity_id',
        'journal_name',
        'short_code',
        'is_system_generated',
        'prefix',
        'voucher_group',
    ];
}
