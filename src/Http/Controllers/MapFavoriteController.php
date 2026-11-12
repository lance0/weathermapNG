<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use Throwable;

/**
 * Per-user map favorites.
 *
 * Unlike the map content mutations, this endpoint is NOT admin-gated: every
 * authenticated user manages only their own rows. It stays inside the route
 * group's `auth` middleware so unauthenticated requests never reach it.
 */
class MapFavoriteController extends Controller
{
    public function toggle(Map $map): JsonResponse
    {
        $userId = auth()->id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $userId = (int) $userId;
        $mapId = (int) $map->getKey();

        try {
            $favorited = DB::transaction(function () use ($userId, $mapId) {
                $now = now();
                // The unique (user_id, map_id) key turns a concurrent
                // double-click into a no-op insert instead of a duplicate or
                // a 500; only the request that actually inserted reports the
                // map as favorited.
                $inserted = DB::table('wmng_map_favorites')->insertOrIgnore([
                    'user_id' => $userId,
                    'map_id' => $mapId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($inserted > 0) {
                    return true;
                }

                DB::table('wmng_map_favorites')
                    ->where('user_id', $userId)
                    ->where('map_id', $mapId)
                    ->delete();

                return false;
            });

            return response()->json([
                'success' => true,
                'favorited' => $favorited,
                'count' => DB::table('wmng_map_favorites')->where('map_id', $mapId)->count(),
            ]);
        } catch (Throwable $e) {
            // Never leak SQL or connection details to the browser.
            Log::error('WeathermapNG: favorite toggle failed', [
                'map_id' => $mapId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not update favorite',
            ], 500);
        }
    }
}
