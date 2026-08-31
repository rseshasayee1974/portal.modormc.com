<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class BankAccountType
 * 
 * @property int $id
 * @property string $type
 *
 * @package App\Models
 */
class BankAccountType extends Model
{
	use HasFactory ,  SoftDeletes, TracksModelChanges;

	protected $table = 'mm_bank_account_types';
	public $timestamps = false;

	protected $fillable = [
		'type',
	];
}
