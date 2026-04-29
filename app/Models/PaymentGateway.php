<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PaymentGateway
 * 
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class PaymentGateway extends Model
{
	use HasFactory;

	protected $table = 'mm_payment_gateways';

	protected $casts = [
		'is_active' => 'int'
	];

	protected $fillable = [
		'name',
		'description',
		'is_active'
	];
}
