<?php

namespace LibreNMS\Plugins\WeathermapNG\Models;

use Illuminate\Database\Eloquent\Model;

class MapTemplate extends Model
{
    protected $table = 'wmng_map_templates';

    protected $fillable = [
        'name',
        'title',
        'description',
        'width',
        'height',
        'config',
        'icon',
        'category',
        'is_built_in',
    ];

    protected $casts = [
        'config' => 'array',
        'is_built_in' => 'boolean',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function scopeBuiltIn($query)
    {
        return $query->where('is_built_in', true);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_built_in', false);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
