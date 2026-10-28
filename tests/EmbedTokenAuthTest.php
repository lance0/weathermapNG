<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Http\Middleware\EmbedTokenAuth;

class EmbedTokenAuthTest extends TestCase
{
    public function test_middleware_registered_with_token_routes(): void
    {
        $routes = file_get_contents(__DIR__ . '/../routes/web.php');
        $this->assertStringContainsString('EmbedTokenAuth::class', $routes);
        // The middleware selects web/auth or token auth for a single route.
        $this->assertStringContainsString('Route::middleware(\\LibreNMS\\Plugins\\WeathermapNG\\Http\\Middleware\\EmbedTokenAuth::class)', $routes);
        $this->assertStringContainsString("Route::middleware(['web', 'auth'])", $routes);
    }

    public function test_token_routes_cover_embed_json_live_sse(): void
    {
        $routes = file_get_contents(__DIR__ . '/../routes/web.php');
        foreach (['embed', 'json', 'live', 'sse'] as $endpoint) {
            $this->assertSame(1, substr_count($routes, "->name('weathermapng.$endpoint')"));
            $path = $endpoint === 'embed' ? 'embed/{map}' : "api/maps/{map}/$endpoint";
            $this->assertSame(1, substr_count($routes, "Route::get('plugin/WeathermapNG/$path'"),
                'Duplicate URLs overwrite the session route, regardless of route name');
        }
    }

    public function test_config_declares_embed_token(): void
    {
        $config = file_get_contents(__DIR__ . '/../config/config.php');
        $this->assertStringContainsString("'embed_token' => env('WEATHERMAPNG_EMBED_TOKEN')", $config);
    }

    public function test_bearer_token_extraction(): void
    {
        $this->assertSame('my-secret-token', EmbedTokenAuth::tokenFrom('Bearer my-secret-token', null));
    }

    public function test_bearer_case_insensitive_scheme(): void
    {
        $this->assertSame('tok', EmbedTokenAuth::tokenFrom('bearer tok', null));
    }

    public function test_query_token_extraction(): void
    {
        $this->assertSame('query-token', EmbedTokenAuth::tokenFrom(null, 'query-token'));
    }

    public function test_bearer_takes_precedence_over_query(): void
    {
        $this->assertSame('header-token', EmbedTokenAuth::tokenFrom('Bearer header-token', 'query-token'));
    }

    public function test_missing_token_returns_null(): void
    {
        $this->assertNull(EmbedTokenAuth::tokenFrom(null, null));
        $this->assertNull(EmbedTokenAuth::tokenFrom('Basic abc', null));
        $this->assertNull(EmbedTokenAuth::tokenFrom(null, ''));
        $this->assertNull(EmbedTokenAuth::tokenFrom(null, ['array']));
    }
}
