<?php
/**
 * WeathermapNG Production Configuration Template
 * Copy this to weathermapng.php and adjust for your environment
 */

return [
    // Basic settings
    'default_width' => 800,
    'default_height' => 600,
    'poll_interval' => 300, // 5 minutes
    
    // Data sources
    'rrd_base' => env('LIBRENMS_RRD_BASE', '/opt/librenms/rrd'),
    'enable_local_rrd' => true,
    'enable_api_fallback' => true,
    'api_token' => env('LIBRENMS_API_TOKEN', ''),
    
    // Performance
    'cache_ttl' => 300,
    'thresholds' => [50, 80, 95],
    
    // Logging (production = minimal logging)
    'debug' => false,
    'log_level' => 'error',
    'log_file' => '/var/log/librenms/weathermapng.log',
    
    // Colors
    'colors' => [
        'node_up' => '#28a745',
        'node_down' => '#dc3545',
        'link_normal' => '#28a745',
        'link_warning' => '#ffc107',
        'link_critical' => '#dc3545',
    ],
    
    // Security
    'allow_embed' => true,
    'max_image_size' => 2048, // KB
];