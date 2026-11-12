#!/usr/bin/env php
<?php
/**
 * Container integration test for per-user map favorites.
 *
 * Run inside the librenms-dev container:
 *   docker exec -u librenms librenms-dev php \
 *     /opt/librenms/html/plugins/WeathermapNG/tests/integration_favorites_test.php
 *
 * Creates two non-admin users and temporary maps, drives the real controller
 * with each user signed in, then cleans up. Exits 0 on success, 1 on failure.
 */

require '/opt/librenms/vendor/autoload.php';
$app = require '/opt/librenms/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapFavoriteController;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\MapFavorite;
use LibreNMS\Plugins\WeathermapNG\Services\TopologyPreview;

$failures = [];

function assert_true($cond, string $msg): void {
    global $failures;
    if (!$cond) {
        $failures[] = $msg;
        echo "FAIL: $msg\n";
    } else {
        echo "PASS: $msg\n";
    }
}

/**
 * Sign a user in for controller/auth checks without an HTTP request.
 */
function loginAs(User $user): void {
    auth()->forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    $session = app('session')->driver();
    $session->start();
    auth()->guard('web')->login($user);
    $session->save();
}

function toggle(Map $map): array {
    $response = (new MapFavoriteController())->toggle($map);

    return [$response->getStatusCode(), $response->getData(true)];
}

function favoriteRows(int $mapId): int {
    return DB::table('wmng_map_favorites')->where('map_id', $mapId)->count();
}

function makeUser(string $prefix): User {
    $user = new User([
        'username' => $prefix . '-' . bin2hex(random_bytes(8)),
        'auth_type' => 'mysql',
        'enabled' => true,
    ]);
    $user->setPassword(bin2hex(random_bytes(16)));
    $user->save();

    return $user;
}

$ownedMaps = [];
$ownedUsers = [];
$createdTable = false;
$cleanup = function () use (&$ownedMaps, &$ownedUsers, &$createdTable) {
    try {
        if (Schema::hasTable('wmng_map_favorites')) {
            foreach ($ownedMaps as $map) {
                DB::table('wmng_map_favorites')->where('map_id', $map->getKey())->delete();
            }
        }
    } catch (Throwable $e) {
        echo "WARN: favorite cleanup failed: " . $e->getMessage() . "\n";
    }
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    foreach ($ownedMaps as $map) {
        try {
            Map::whereKey($map->getKey())->delete();
        } catch (Throwable $e) {
        }
    }
    foreach ($ownedUsers as $user) {
        try {
            User::whereKey($user->getKey())->delete();
        } catch (Throwable $e) {
        }
    }
    if ($createdTable) {
        try {
            Schema::dropIfExists('wmng_map_favorites');
        } catch (Throwable $e) {
        }
    }
};

try {
    // The dev stack may predate this table; create it exactly like setup.php.
    if (!Schema::hasTable('wmng_map_favorites')) {
        (require __DIR__ . '/../database/migrations/2026_11_12_000001_create_map_favorites_table.php')->up();
        $createdTable = true;
    }
    assert_true(Schema::hasTable('wmng_map_favorites'), 'favorites table exists');

    $userA = makeUser('wmng-fav-a');
    $userB = makeUser('wmng-fav-b');
    $ownedUsers = [$userA, $userB];
    $nonAdmin = fn(User $user) => !(method_exists($user, 'isAdmin') && $user->isAdmin());
    assert_true($nonAdmin($userA) && $nonAdmin($userB), 'fixture users are not admins');

    $map = Map::create([
        'name' => 'integration-fav-map-' . bin2hex(random_bytes(6)),
        'title' => 'Favorites Integration Map',
        'options' => ['width' => 800, 'height' => 600, 'background' => '#ffffff'],
    ]);
    $ownedMaps[] = $map;

    // --- 1: user A favorites the map ---
    loginAs($userA);
    [$status, $data] = toggle($map);
    assert_true($status === 200, 'toggle returns HTTP 200');
    assert_true(($data['success'] ?? null) === true, 'toggle response has success=true');
    assert_true(($data['favorited'] ?? null) === true, 'first toggle favorites the map');
    assert_true(($data['count'] ?? null) === 1, 'count reflects one favorite');
    assert_true(favoriteRows($map->id) === 1, 'exactly one favorite row exists');
    assert_true(MapFavorite::where('user_id', $userA->user_id)->where('map_id', $map->id)->exists(), 'row belongs to user A');

    // --- 2: user B favorites the same map; A's row is untouched ---
    loginAs($userB);
    [$status, $data] = toggle($map);
    assert_true($status === 200 && ($data['favorited'] ?? null) === true, 'second user can favorite the same map');
    assert_true(($data['count'] ?? null) === 2, 'count reflects both users');
    assert_true(MapFavorite::where('user_id', $userA->user_id)->where('map_id', $map->id)->exists(), 'user A favorite survives user B toggle');
    assert_true(MapFavorite::where('user_id', $userB->user_id)->where('map_id', $map->id)->exists(), 'user B favorite stored');

    // --- 3: B's second toggle removes only B's row ---
    [$status, $data] = toggle($map);
    assert_true($status === 200 && ($data['favorited'] ?? null) === false, 'second toggle unfavorites');
    assert_true(($data['count'] ?? null) === 1, 'count drops back to one');
    assert_true(!MapFavorite::where('user_id', $userB->user_id)->where('map_id', $map->id)->exists(), 'user B row removed');
    assert_true(MapFavorite::where('user_id', $userA->user_id)->where('map_id', $map->id)->exists(), 'user A row is isolated from user B changes');

    // --- 4: repeated toggles stay idempotent on the unique key ---
    loginAs($userA);
    toggle($map); // A unfavorites
    assert_true(favoriteRows($map->id) === 0, 'A unfavorite leaves no rows');
    toggle($map); // A favorites again
    assert_true(favoriteRows($map->id) === 1, 'A refavorite stores exactly one row');
    $duplicate = DB::table('wmng_map_favorites')->insertOrIgnore([
        'user_id' => $userA->user_id,
        'map_id' => $map->id,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    assert_true($duplicate === 0 && favoriteRows($map->id) === 1, 'unique (user_id, map_id) blocks duplicate favorites');

    // --- 5: favoritedBy scope and batched index data ---
    assert_true(Map::favoritedBy($userA->user_id)->whereKey($map->id)->exists(), 'favoritedBy scope finds the favorited map');
    assert_true(!Map::favoritedBy(null)->whereKey($map->id)->exists(), 'favoritedBy(null) matches nothing');
    $index = TopologyPreview::loadMaps($userA->user_id);
    $card = $index['maps']->firstWhere('id', $map->id);
    assert_true($card !== null && $card->favorited === true, 'index data marks the map favorited for A');
    assert_true($card !== null && $card->favorite_count === 1, 'index data carries the favorites count');
    assert_true(isset($index['previews'][$map->id]), 'topology preview remains available with favorites');

    // --- 6: deleting a map cascades its favorites ---
    $mapCascade = Map::create([
        'name' => 'integration-fav-cascade-' . bin2hex(random_bytes(6)),
        'title' => 'Favorites Cascade Map',
        'options' => ['width' => 800, 'height' => 600, 'background' => '#ffffff'],
    ]);
    $ownedMaps[] = $mapCascade;
    loginAs($userA);
    toggle($mapCascade);
    loginAs($userB);
    toggle($mapCascade);
    assert_true(favoriteRows($mapCascade->id) === 2, 'cascade map has two favorites before delete');
    $mapCascade->delete();
    assert_true(favoriteRows($mapCascade->id) === 0, 'deleting a map cascades favorite rows');

    // --- 7: unauthenticated requests are rejected, never served ---
    auth()->guard('web')->logout();
    auth()->forgetGuards();
    [$status, $data] = toggle($map);
    assert_true($status === 401, 'unauthenticated toggle returns HTTP 401');
    assert_true(($data['success'] ?? null) === false, 'unauthenticated response reports failure');

    // --- 8: route wiring ---
    $route = app('router')->getRoutes()->getByName('weathermapng.map.favorite');
    assert_true($route !== null, 'favorite route is registered');
    assert_true($route !== null && in_array('POST', $route->methods(), true), 'favorite route accepts POST');
    assert_true($route !== null && str_contains($route->uri(), 'map/{map}/favorite'), 'favorite route path matches the documented URL');

    echo "\n";
    if (empty($failures)) {
        echo "All favorites integration tests passed.\n";
        $exitCode = 0;
    } else {
        echo count($failures) . " failure(s):\n";
        foreach ($failures as $f) echo "  - $f\n";
        $exitCode = 1;
    }
} catch (Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    $exitCode = 1;
} finally {
    $cleanup();
}

exit($exitCode);
