<?php

namespace LibreNMS\Plugins\WeathermapNG;

use Illuminate\Database\Eloquent\Model;

class Map extends Model
{
    protected $table = 'wmng_maps';
    protected $fillable = ['name', 'title', 'options'];

    public function nodes()
    {
        return $this->hasMany(Node::class);
    }

    public function links()
    {
        return $this->hasMany(Link::class);
    }
}