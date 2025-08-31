<?php
/**
 * WeathermapNG Logging Configuration
 * Supports both traditional and structured JSON logging
 */

return [
    // Logging format: 'json' for structured logging, 'text' for traditional
    'format' => env('WEATHERMAP_LOG_FORMAT', 'json'),
    
    // Log level: debug, info, warning, error, critical
    'level' => env('WEATHERMAP_LOG_LEVEL', 'info'),
    
    // Log output destination
    'output' => env('WEATHERMAP_LOG_OUTPUT', '/var/log/librenms/weathermapng.log'),
    
    // Use stdout for Docker containers
    'use_stdout' => env('WEATHERMAP_LOG_STDOUT', false),
    
    // Log rotation settings
    'rotation' => [
        'enabled' => env('WEATHERMAP_LOG_ROTATE', true),
        'max_files' => env('WEATHERMAP_LOG_MAX_FILES', 7),
        'max_size' => env('WEATHERMAP_LOG_MAX_SIZE', '10M'),
    ],
    
    // Structured logging fields to include
    'structured_fields' => [
        'timestamp' => true,
        'level' => true,
        'message' => true,
        'context' => true,
        'hostname' => true,
        'process_id' => true,
        'memory_usage' => true,
        'execution_time' => true,
    ],
    
    // Performance logging
    'performance' => [
        'enabled' => env('WEATHERMAP_LOG_PERFORMANCE', true),
        'slow_query_threshold' => 1000, // milliseconds
        'memory_threshold' => 100, // MB
    ],
    
    // Security logging
    'security' => [
        'log_auth_attempts' => true,
        'log_permission_denials' => true,
        'mask_sensitive_data' => true,
    ],
    
    // Error tracking
    'error_tracking' => [
        'enabled' => env('WEATHERMAP_ERROR_TRACKING', false),
        'service' => env('WEATHERMAP_ERROR_SERVICE', 'sentry'), // sentry, rollbar, etc.
        'dsn' => env('WEATHERMAP_ERROR_DSN', ''),
    ],
];