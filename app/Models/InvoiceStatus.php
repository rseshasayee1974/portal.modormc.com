<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

/**
 * Class InvoiceStatus
 * 
 * @property int $id
 * @property string $status_name
 *
 * @package App\Models
 */
class InvoiceStatus extends Model
{
	use HasFactory;

	protected $table = 'mm_invoice_statuses';
	public $timestamps = false;

	protected $fillable = [
		'status_name'
	];
}
