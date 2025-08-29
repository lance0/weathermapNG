<?php
// config/settings.php
return [
    'map_dir' => __DIR__ . '/maps/',
    'output_dir' => __DIR__ . '/output/maps/',
    'thumbnail_dir' => __DIR__ . '/output/thumbnails/',
    'poll_interval' => 300, // 5 minutes
    'default_width' => 800,
    'default_height' => 600,
    'rrd_path' => '/opt/librenms/rrd',
    'api_token' => env('LIBRENMS_API_TOKEN'),
    'enable_local_rrd' => true,
    'enable_api_fallback' => true,
    'cache_ttl' => 300, // 5 minutes
    'security' => [
        'allow_embed' => true,
        'embed_domains' => ['localhost', '*.yourdomain.com'],
        'max_image_size' => 2048, // KB
    ],
    'rendering' => [
        'image_format' => 'png',
        'quality' => 90,
        'font_size' => 10,
        'node_radius' => 10,
        'link_width' => 2,
    ],
    'colors' => [
        'node_up' => '#28a745',
        'node_down' => '#dc3545',
        'node_unknown' => '#6c757d',
        'link_normal' => '#28a745',
        'link_warning' => '#ffc107',
        'link_critical' => '#dc3545',
        'background' => '#ffffff',
    ]
];