<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class PaymentStatus
 * 
 * @property int $id
 * @property string $status_name
 *
 * @package App\Models
 */
class PaymentStatus extends Model
{
	use HasFactory, SoftDeletes;

	protected $table = 'mm_payment_statuses';

	protected $fillable = [
		'status_name'
	];
}
