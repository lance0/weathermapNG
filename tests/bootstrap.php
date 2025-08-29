<?php
// Minimal bootstrap for PHPUnit in plugin context

// Ensure Composer autoload is available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require __DIR__ . '/../../vendor/autoload.php';
}

// Shim Laravel's config() helper used by lib classes
if (!function_exists('config')) {
    function config($key = null, $default = null) {
        static $cfg = null;
        if ($cfg === null) {
            $cfg = [
                'weathermapng.default_width' => 800,
                'weathermapng.default_height' => 600,
                'weathermapng.colors.link_normal' => '#28a745',
                'weathermapng.colors.link_warning' => '#ffc107',
                'weathermapng.colors.link_critical' => '#dc3545',
                'weathermapng.colors.node_down' => '#dc3545',
                'weathermapng.colors.node_unknown' => '#6c757d',
                'weathermapng.rendering.link_width' => 2,
            ];
        }
        if ($key === null) return $cfg;
        return $cfg[$key] ?? $default;
    }
}
