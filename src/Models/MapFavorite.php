<?php

namespace LibreNMS\Plugins\WeathermapNG\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single user's favorite of a map. `user_id` references LibreNMS users but
 * carries no foreign-key constraint: plugin tables must not depend on the
 * users table's exact name or engine in every install.
 */
class MapFavorite extends Model
{
    protected $table = 'wmng_map_favorites';

    protected $fillable = ['user_id', 'map_id'];

    protected $casts = [
        'user_id' => 'integer',
        'map_id' => 'integer',
    ];

    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class);
    }
}
