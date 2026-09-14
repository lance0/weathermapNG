<?php

namespace LibreNMS\Plugins\WeathermapNG;

/**
 * Cache-busting asset URLs.
 *
 * LibreNMS serves plugin assets straight from the plugin directory with no
 * Cache-Control header, so browsers rely on heuristic caching. After a
 * plugin upgrade that can keep serving a stale `embed-app.js` etc. for the
 * length of the heuristic lifetime. Appending the asset's mtime as a query
 * string makes every changed file a new URL, ending the staleness window.
 */
final class Asset
{
    /** @var array<string, string> path → versioned URL (request-local) */
    private static array $cache = [];

    public static function url(string $path): string
    {
        $key = '/' . ltrim($path, '/');
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $file = dirname(__DIR__) . '/resources' . $key;
        $version = is_file($file) ? '?v=' . filemtime($file) : '';

        $url = asset('plugins/WeathermapNG/resources' . $key) . $version;
        self::$cache[$key] = $url;

        return $url;
    }
}
