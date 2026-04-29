<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class EntityBankAccount
 * 
 * @property int $id
 * @property int $entity_id
 * @property int $account_type
 * @property string $account_number
 * @property string $bank_name
 * @property string|null $bank_branch
 * @property string|null $ifsc_code
 * @property string|null $bank_address
 * @property int $is_primary
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @package App\Models
 */
class EntityBankAccount extends Model
{
	use HasFactory;

	use SoftDeletes;
	protected $table = 'mm_entity_bank_accounts';

	protected $casts = [
		'entity_id' => 'int',
		'account_type' => 'int',
		'is_primary' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'entity_id',
		'account_type',
		'account_number',
		'bank_name',
		'bank_branch',
		'ifsc_code',
		'bank_address',
		'is_primary',
		'created_by',
		'updated_by',
		'deleted_by'
	];
}
