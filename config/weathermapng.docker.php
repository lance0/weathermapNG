<?php
/**
 * WeathermapNG Docker Configuration
 *
 * This configuration file is optimized for Docker container environments.
 * Copy this to config/weathermapng.php when using Docker.
 */

return [
    // Docker mode enabled
    'docker_mode' => true,

    // Basic map settings
    'default_width' => 800,
    'default_height' => 600,
    'poll_interval' => 300, // 5 minutes

    // Utilization thresholds (%)
    'thresholds' => [50, 80, 95],
    'scale' => 'bits',

    // RRD file location (adjust for your container)
    'rrd_base' => env('LIBRENMS_RRD_BASE', '/opt/librenms/rrd'),
    'enable_local_rrd' => true,
    'enable_api_fallback' => true,
    'cache_ttl' => 300,

    // Status colors
    'colors' => [
        'node_up' => '#28a745',
        'node_down' => '#dc3545',
        'node_warning' => '#ffc107',
        'node_unknown' => '#6c757d',
        'link_normal' => '#28a745',
        'link_warning' => '#ffc107',
        'link_critical' => '#dc3545',
        'background' => '#ffffff',
    ],

    // Rendering settings
    'rendering' => [
        'image_format' => 'png',
        'quality' => 90,
        'font_size' => 10,
        'node_radius' => 10,
        'link_width' => 2,
    ],

    // Security settings
    'security' => [
        'allow_embed' => true,
        'embed_domains' => ['localhost', '*.yourdomain.com'],
        'max_image_size' => 2048, // KB
    ],

    // Editor settings
    'editor' => [
        'grid_size' => 20,
        'snap_to_grid' => true,
        'auto_save' => true,
        'auto_save_interval' => 30, // seconds
    ],

    // Docker-specific settings
    'log_to_stdout' => env('LOG_TO_STDOUT', true),
    'log_file' => env('WEATHERMAP_LOG', '/dev/stdout'),
    'output_path' => env('WEATHERMAP_OUTPUT', '/opt/librenms/html/plugins/WeathermapNG/output'),

    // Database connection (use container networking)
    'database' => [
        'host' => env('DB_HOST', 'db'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'librenms'),
        'username' => env('DB_USERNAME', 'librenms'),
        'password' => env('DB_PASSWORD'),
    ],
];