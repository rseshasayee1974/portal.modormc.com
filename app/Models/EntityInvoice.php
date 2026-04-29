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
 * Class EntityInvoice
 * 
 * @property int $id
 * @property int $entity_id
 * @property int $subscription_id
 * @property string $invoice_no
 * @property float $amount
 * @property float $tax_amount
 * @property int $currency_id
 * @property int $invoice_status
 * @property Carbon $issued_at
 * @property Carbon $due_date
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
class EntityInvoice extends Model
{
	use HasFactory;

	use SoftDeletes;
	protected $table = 'mm_entity_invoices';

	protected $casts = [
		'entity_id' => 'int',
		'subscription_id' => 'int',
		'amount' => 'float',
		'tax_amount' => 'float',
		'currency_id' => 'int',
		'invoice_status' => 'int',
		'issued_at' => 'datetime',
		'due_date' => 'datetime',
		'paid_at' => 'datetime',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'entity_id',
		'subscription_id',
		'invoice_no',
		'amount',
		'tax_amount',
		'currency_id',
		'invoice_status',
		'issued_at',
		'due_date',
		'paid_at',
		'created_by',
		'updated_by',
		'deleted_by'
	];
}
