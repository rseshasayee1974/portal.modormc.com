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
 * Class InvoicePayment
 * 
 * @property int $id
 * @property int $invoice_id
 * @property int $gateway_id
 * @property string $transaction_ref
 * @property float $amount
 * @property int $payment_status_id
 * @property Carbon|null $paid_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @package App\Models
 */
class InvoicePayment extends Model
{
	use HasFactory;

	use SoftDeletes;
	protected $table = 'mm_invoice_payments';

	protected $casts = [
		'invoice_id' => 'int',
		'gateway_id' => 'int',
		'amount' => 'float',
		'payment_status_id' => 'int',
		'paid_at' => 'datetime',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'invoice_id',
		'gateway_id',
		'transaction_ref',
		'amount',
		'payment_status_id',
		'paid_at',
		'created_by',
		'updated_by',
		'deleted_by'
	];
}
