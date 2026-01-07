<?php

namespace LibreNMS\Plugins\WeathermapNG\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapVersion extends Model
{
    protected $table = 'wmng_map_versions';

    protected $fillable = [
        'map_id',
        'name',
        'description',
        'config_snapshot',
        'created_by',
    ];

    protected $casts = [
        'config_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    protected $appends = [
        'created_at_human',
    ];

    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function getCreatedAtHumanAttribute(): string
    {
        return $this->created_at ? $this->created_at->diffForHumans() : 'N/A';
    }

    public function scopeLatestForMap($query, $mapId)
    {
        return $query->where('map_id', $mapId)
            ->orderBy('created_at', 'desc');
    }

    public function scopeVersions($query, $mapId, $limit = 10)
    {
        return $query->where('map_id', $mapId)
            ->orderBy('created_at', 'desc')
            ->limit($limit);
    }

    public function scopeByVersionNumber($query, $mapId, $versionNumber)
    {
        return $query->where('map_id', $mapId)
            ->latest();
    }
}
