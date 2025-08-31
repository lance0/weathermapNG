<?php
/**
 * WeathermapNG Secrets Management Configuration
 * 
 * This file demonstrates secure credential management practices.
 * In production, use environment variables or external secret stores.
 */

return [
    // Database credentials - use environment variables
    'database' => [
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'librenms'),
        'username' => env('DB_USERNAME', 'librenms'),
        'password' => env('DB_PASSWORD', ''), // Never hardcode passwords
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
    
    // API credentials
    'api' => [
        'librenms_token' => env('LIBRENMS_API_TOKEN', ''),
        'external_api_key' => env('EXTERNAL_API_KEY', ''),
    ],
    
    // Encryption keys
    'encryption' => [
        'key' => env('APP_KEY', ''), // 32-character random string
        'cipher' => 'AES-256-CBC',
    ],
    
    // External secret management integration
    'secret_manager' => [
        'provider' => env('SECRET_PROVIDER', 'env'), // env, vault, aws_secrets, azure_keyvault
        'vault' => [
            'address' => env('VAULT_ADDR', 'https://vault.example.com'),
            'token' => env('VAULT_TOKEN', ''),
            'path' => env('VAULT_PATH', 'secret/weathermapng'),
        ],
        'aws_secrets' => [
            'region' => env('AWS_REGION', 'us-east-1'),
            'secret_name' => env('AWS_SECRET_NAME', 'weathermapng'),
        ],
        'azure_keyvault' => [
            'vault_url' => env('AZURE_KEYVAULT_URL', ''),
            'tenant_id' => env('AZURE_TENANT_ID', ''),
            'client_id' => env('AZURE_CLIENT_ID', ''),
            'client_secret' => env('AZURE_CLIENT_SECRET', ''),
        ],
    ],
    
    // Redis cache credentials
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => env('REDIS_DB', 0),
    ],
    
    // Session configuration
    'session' => [
        'secure' => env('SESSION_SECURE_COOKIE', true), // HTTPS only
        'http_only' => true, // Prevent XSS
        'same_site' => 'lax', // CSRF protection
        'lifetime' => 120, // minutes
    ],
    
    // Security headers
    'security_headers' => [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
    ],
];