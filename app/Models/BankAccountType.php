<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

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
	use HasFactory;

	protected $table = 'mm_bank_account_types';
	public $timestamps = false;

	protected $fillable = [
		'type'
	];
}
