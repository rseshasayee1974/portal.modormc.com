<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
	protected $table = 'mm_menus';
    protected $fillable = [
        'menutype',
        'title',
        'alias',
        'link',
        'icon',
        'published',
        'parent_id',
        'level',
        'ordering',
        'permission_name',
        'entity_id',
    ];

    protected $casts = [
        'published' => 'boolean',
    ];

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('ordering');
    }

    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }
}
