<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TelescopeEntriesTag
 * 
 * @property string $entry_uuid
 * @property string $tag
 *
 * @package App\Models
 */
class TelescopeEntriesTag extends Model
{
	use HasFactory;

	protected $table = 'mm_telescope_entries_tags';
	public $incrementing = false;
	public $timestamps = false;
}
