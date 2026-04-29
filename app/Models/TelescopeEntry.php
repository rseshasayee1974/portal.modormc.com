<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class TelescopeEntry
 * 
 * @property int $sequence
 * @property string $uuid
 * @property string $batch_id
 * @property string|null $family_hash
 * @property bool $should_display_on_index
 * @property string $type
 * @property string $content
 * @property Carbon|null $created_at
 *
 * @package App\Models
 */
class TelescopeEntry extends Model
{
	use HasFactory;

	protected $table = 'mm_telescope_entries';
	protected $primaryKey = 'sequence';
	public $timestamps = false;

	protected $casts = [
		'should_display_on_index' => 'bool'
	];

	protected $fillable = [
		'uuid',
		'batch_id',
		'family_hash',
		'should_display_on_index',
		'type',
		'content'
	];
}
