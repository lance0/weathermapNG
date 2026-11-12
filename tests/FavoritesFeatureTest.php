<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\MapFavorite;
use PHPUnit\Framework\TestCase;

/**
 * Per-user map favorites.
 *
 * Schema, model, and scope behavior run against in-memory SQLite through the
 * real migration. Route/controller/index wiring uses file-content checks
 * because Laravel's full HTTP stack only exists in the LibreNMS container
 * (see tests/integration_favorites_test.php for the live-auth suite).
 */
class FavoritesFeatureTest extends TestCase
{
    /**
     * Boot an isolated in-memory SQLite schema and run the real favorites
     * migration. Only tests that assert behavior need the database; the
     * wiring tests run everywhere.
     */
    private function bootDatabase(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension not available; favorites are covered by the container integration suite.');
        }

        $app = Facade::getFacadeApplication();
        $capsule = new Capsule($app);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $app->instance('db', $capsule->getDatabaseManager());
        $app->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());
        Facade::clearResolvedInstance('db');
        Facade::clearResolvedInstance('db.schema');

        (require __DIR__ . '/../database/migrations/2025_08_29_000001_create_weathermapng_tables.php')->up();
        (require __DIR__ . '/../database/migrations/2026_09_14_000001_add_parent_map_id_to_wmng_maps.php')->up();
        (require __DIR__ . '/../database/migrations/2026_11_12_000001_create_map_favorites_table.php')->up();
    }

    private function map(string $name): Map
    {
        return Map::create(['name' => $name, 'title' => strtoupper($name)]);
    }

    public function testMigrationCreatesOwnedUniqueFavoritesTable(): void
    {
        $this->bootDatabase();

        $this->assertTrue(Schema::hasTable('wmng_map_favorites'));
        $columns = array_map(fn($c) => strtolower($c), Schema::getColumnListing('wmng_map_favorites'));
        foreach (['id', 'user_id', 'map_id', 'created_at', 'updated_at'] as $column) {
            $this->assertContains($column, $columns);
        }

        $map = $this->map('migration-map');
        $row = ['user_id' => 5, 'map_id' => $map->id, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
        $this->assertSame(1, DB::table('wmng_map_favorites')->insertOrIgnore($row));
        // The unique (user_id, map_id) key makes a repeated toggle a no-op
        // insert instead of a duplicate row or a 500.
        $this->assertSame(0, DB::table('wmng_map_favorites')->insertOrIgnore($row));
        $this->assertSame(1, DB::table('wmng_map_favorites')->count());
    }

    public function testMapFavoriteModelIsMassAssignableAndCastsIds(): void
    {
        $this->bootDatabase();

        $map = $this->map('model-map');
        $favorite = MapFavorite::create(['user_id' => '12', 'map_id' => $map->id]);

        $this->assertSame('wmng_map_favorites', $favorite->getTable());
        $this->assertSame(12, $favorite->user_id);
        $this->assertSame((int) $map->id, $favorite->map_id);
        $this->assertSame($map->id, $favorite->map->id);
        $this->assertSame(1, $map->favorites()->count());
    }

    public function testFavoritedByScopeIsolatesUsersAndGuests(): void
    {
        $this->bootDatabase();

        $shared = $this->map('shared-map');
        $other = $this->map('other-map');
        MapFavorite::create(['user_id' => 7, 'map_id' => $shared->id]);
        MapFavorite::create(['user_id' => 8, 'map_id' => $shared->id]);
        MapFavorite::create(['user_id' => 8, 'map_id' => $other->id]);

        $this->assertSame([$shared->id], Map::favoritedBy(7)->pluck('id')->all());
        $this->assertSame([$other->id, $shared->id], Map::favoritedBy(8)->orderBy('name')->pluck('id')->all());
        $this->assertSame([], Map::favoritedBy(9)->pluck('id')->all());
        // A guest must never match rows because user_id happens to be absent.
        $this->assertSame([], Map::favoritedBy(null)->pluck('id')->all());
    }

    public function testDeletingAMapCascadesItsFavorites(): void
    {
        $this->bootDatabase();

        $map = $this->map('cascade-map');
        MapFavorite::create(['user_id' => 7, 'map_id' => $map->id]);
        MapFavorite::create(['user_id' => 8, 'map_id' => $map->id]);

        $map->delete();

        $this->assertSame(0, DB::table('wmng_map_favorites')->where('map_id', $map->id)->count());
    }

    public function testFavoriteRouteLivesInAuthGroupAndHasNoAdminGate(): void
    {
        $routes = file_get_contents(__DIR__ . '/../routes/web.php');
        $authGroup = strpos($routes, "Route::middleware(['web', 'auth'])");
        $favorite = strpos($routes, "plugin/WeathermapNG/map/{map}/favorite");
        $embedGroup = strpos($routes, 'EmbedTokenAuth::class');

        $this->assertNotFalse($authGroup);
        $this->assertNotFalse($favorite, 'favorite route must be registered');
        $this->assertNotFalse($embedGroup);
        $this->assertGreaterThan($authGroup, $favorite, 'favorite route sits inside the auth group');
        $this->assertLessThan($embedGroup, $favorite, 'favorite route is not in the token-auth group');
        $this->assertMatchesRegularExpression(
            "/Route::post\\('plugin\\/WeathermapNG\\/map\\/\\{map\\}\\/favorite', \\[MapFavoriteController::class, 'toggle'\\]\\)/",
            $routes
        );
        $this->assertStringContainsString('MapFavoriteController', $routes);

        $controller = file_get_contents(__DIR__ . '/../src/Http/Controllers/MapFavoriteController.php');
        $this->assertStringNotContainsString('requireAdmin', $controller, 'favorites are per-user, never admin-gated');
        $this->assertStringContainsString('auth()->id()', $controller);
        $this->assertStringContainsString("'favorited' => \$favorited", $controller);
        $this->assertStringContainsString("'count' =>", $controller);
        $this->assertStringContainsString('insertOrIgnore', $controller);
        $this->assertStringContainsString('wmng_map_favorites', $controller);
        $this->assertStringContainsString('401', $controller);
    }

    public function testPageControllerSuppliesCurrentUserFavorites(): void
    {
        $page = file_get_contents(__DIR__ . '/../src/Http/Controllers/PageController.php');
        $this->assertStringContainsString('TopologyPreview::loadMaps(', $page);
        $this->assertStringContainsString('auth()->id()', $page);

        $preview = file_get_contents(__DIR__ . '/../src/Services/TopologyPreview.php');
        $this->assertStringContainsString('loadMaps(?int $userId = null)', $preview);
        $this->assertStringContainsString('favorite_count', $preview);
        $this->assertStringContainsString('->groupBy(\'map_id\')', $preview);
        $this->assertStringContainsString("whereIn('map_id', \$mapIds)", $preview);
        $this->assertSame(2, substr_count($preview, 'MapFavorite::'), 'favorites use one count query plus at most one user query');
        // The per-map loop must consume precomputed values, not query.
        $loopStart = strrpos($preview, 'foreach ($maps as $map) {');
        $loopEnd = strpos($preview, 'private static function coordinate');
        $this->assertNotFalse($loopStart);
        $this->assertNotFalse($loopEnd);
        $loop = substr($preview, $loopStart, $loopEnd - $loopStart);
        $this->assertStringContainsString('$map->setAttribute(\'favorited\'', $loop);
        $this->assertStringNotContainsString('MapFavorite::', $loop);
    }

    public function testSetupCreatesFavoritesTableIdempotently(): void
    {
        $setup = file_get_contents(__DIR__ . '/../database/setup.php');
        $this->assertStringContainsString("if (!Schema::hasTable('wmng_map_favorites'))", $setup);
        $this->assertStringContainsString("Schema::create('wmng_map_favorites'", $setup);
        $this->assertStringContainsString("\$t->unique(['user_id', 'map_id']);", $setup);
        $this->assertStringContainsString("onDelete('cascade')", $setup);
        $this->assertFileExists(__DIR__ . '/../database/migrations/2026_11_12_000001_create_map_favorites_table.php');
    }

    public function testIndexRendersAccessibleStarAndFavoritesFilter(): void
    {
        $index = index_source();

        $this->assertStringContainsString('data-action="toggle-favorite"', $index);
        $this->assertStringContainsString('aria-pressed=', $index);
        $this->assertStringContainsString("'Unfavorite' : 'Favorite'", $index, 'visible label swaps with favorite state');
        $this->assertStringContainsString('data-favorited=', $index);
        $this->assertStringContainsString('id="map-favorites-filter"', $index);
        $this->assertStringContainsString('data-favorite-count', $index);
        $this->assertStringContainsString('map-favorite-count-value', $index);

        // JS behavior: optimistic flip, server reconciliation, rollback + toast.
        $this->assertStringContainsString('function toggleFavorite(', $index);
        $this->assertStringContainsString("method: 'POST'", $index);
        $this->assertStringContainsString('/favorite`', $index);
        $this->assertStringContainsString('applyFavoriteState(button, wasFavorited, previousCount)', $index);
        $this->assertStringContainsString("WMNGToast.error('Could not update favorite:", $index);
        $this->assertStringContainsString("favoritesFilter.value === 'favorites'", $index);
        $this->assertStringContainsString("document.addEventListener('wmng:favorites-changed', applyFilter)", $index);
    }
}
