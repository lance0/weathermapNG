<?php

// Mock LibreNMS functions for testing
// Only define these if they don't already exist

if (!function_exists('dbFetchRow')) {
    function dbFetchRow($query, $params = []) {
        // Mock implementation for tests
        return null;
    }
}

if (!function_exists('dbFetchRows')) {
    function dbFetchRows($query, $params = []) {
        // Mock implementation for tests
        return [];
    }
}

if (!function_exists('dbFetchCell')) {
    function dbFetchCell($query, $params = []) {
        // Mock implementation for tests
        return null;
    }
}

if (!function_exists('dbInsert')) {
    function dbInsert($data, $table) {
        // Mock implementation for tests
        return 1;
    }
}

if (!function_exists('dbUpdate')) {
    function dbUpdate($data, $table, $where = '') {
        // Mock implementation for tests
        return true;
    }
}

if (!function_exists('dbDelete')) {
    function dbDelete($table, $where = '') {
        // Mock implementation for tests
        return true;
    }
}

if (!function_exists('config')) {
    function config($key, $default = null) {
        static $config = [
            'weathermapng' => [
                'cache_ttl' => 0,
                'enable_local_rrd' => false,
                'enable_api_fallback' => false,
                'default_width' => 800,
                'default_height' => 600,
                'rrd_base' => '/tmp',
                'api_token' => null,
            ],
            'database' => [
                'connections' => [
                    'testing' => true
                ]
            ]
        ];

        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
}

// Mock Illuminate\Support\Facades\DB for testing
if (!class_exists('\Illuminate\Support\Facades\DB')) {
    class DB {
        public static function statement($query) {
            // Mock implementation
            return true;
        }

        public static function table($table) {
            return new DBTable($table);
        }
    }
}

class DBTable {
    private $table;

    public function __construct($table) {
        $this->table = $table;
    }

    public function truncate() {
        // Mock implementation
        return true;
    }
}