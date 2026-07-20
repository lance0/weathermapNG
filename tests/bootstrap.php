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

// Shim Laravel's auth() helper used by Settings::authorize()
// auth() returns a Guard; auth()->user() returns the authenticated User (or null).
if (!function_exists('auth')) {
    function auth(?string $guard = null): ?object {
        $user = $GLOBALS['__test_auth_resolver'] ? ($GLOBALS['__test_auth_resolver'])() : null;
        return new class($user) {
            private $user;
            public function __construct($user) { $this->user = $user; }
            public function user() { return $this->user; }
        };
    }
}

// Stub Illuminate\Foundation\Auth\User so the type-hint in Settings::authorize()
// resolves in standalone PHPUnit (real class only exists in the LibreNMS container).
if (!class_exists('Illuminate\Foundation\Auth\User')) {
    class SettingsTestStubUser
    {
        private array $attrs;
        public function __construct(array $attrs = []) { $this->attrs = $attrs; }
        public function hasRole(string $role): bool { return ($this->attrs['role'] ?? null) === $role; }
        public function hasGlobalAdmin(): bool { return ($this->attrs['role'] ?? null) === 'admin'; }
        public function isAdmin(): bool { return ($this->attrs['role'] ?? null) === 'admin'; }
    }
    class_alias(SettingsTestStubUser::class, 'Illuminate\Foundation\Auth\User');
}

// Shim Laravel facade application so Log/Cache facades work in standalone tests
if (class_exists('Illuminate\Support\Facades\Facade') && class_exists('Illuminate\Container\Container')) {
    $container = new Illuminate\Container\Container();
    $logger = new \Psr\Log\NullLogger();
    $container->instance('log', $logger);
    $container->instance(\Psr\Log\LoggerInterface::class, $logger);
    $cacheRepo = new class {
        private array $store = [];
        public function remember($key, $ttl, $callback) { return $callback(); }
        public function get($key) { return $this->store[$key] ?? null; }
        public function put($key, $value, $ttl = null) { $this->store[$key] = $value; }
        public function forget($key) { unset($this->store[$key]); }
        public function flush() { $this->store = []; }
    };
    $container->instance('cache', $cacheRepo);
    $container->instance('cache.store', $cacheRepo);
    Illuminate\Support\Facades\Facade::setFacadeApplication($container);
}
