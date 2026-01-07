<?php
return [
    'demo_mode' => env('WEATHERMAPNG_DEMO_MODE', false), // Enable simulated traffic data
    'default_width' => 800,
    'default_height' => 600,
    'poll_interval' => 300,         // seconds for CLI poller default
    'thresholds'    => [50,80,95],  // % utilization thresholds
    'scale'         => 'bits',      // 'bits' or 'bytes'
    'rrd_base'      => '/opt/librenms/rrd',
    'rrdcached'     => [
        'socket' => null,           // e.g. /var/run/rrdcached.sock
    ],
    'enable_local_rrd' => true,     // Use local RRD files
    'enable_api_fallback' => true,  // Fallback to LibreNMS API
    'cache_ttl' => 300,             // Cache TTL in seconds
    'enable_sse' => true,           // Enable Server-Sent Events for live updates
    'client_refresh' => 60,         // Seconds for client polling fallback
    'snmp' => [
        'enabled' => false,         // Enable SNMP fallback for live data if RRD/API unavailable
        'version' => '2c',          // SNMP version
        'community' => env('WEATHERMAPNG_SNMP_COMMUNITY'),
        'timeout' => 1,
        'retries' => 1,
    ],
    'api_token' => env('LIBRENMS_API_TOKEN'),
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
    'rendering' => [
        'image_format' => 'png',
        'quality' => 90,
        'font_size' => 10,
        'node_radius' => 10,
        'link_width' => 2,
    ],
    'security' => [
        'allow_embed' => true,
        'embed_domains' => ['localhost', '*.yourdomain.com'],
        'max_image_size' => 2048, // KB
    ],
    'editor' => [
        'grid_size' => 20,
        'snap_to_grid' => true,
        'auto_save' => true,
        'auto_save_interval' => 30, // seconds
    ],
];
