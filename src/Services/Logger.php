<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\HostnameProcessor;

class Logger
{
    private static ?Logger $instance = null;
    private MonologLogger $logger;
    private array $config;
    private float $startTime;

    private function __construct()
    {
        $this->startTime = microtime(true);
        $this->loadConfig();
        $this->initializeLogger();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadConfig(): void
    {
        $configPath = __DIR__ . '/../config/logging.php';
        $this->config = file_exists($configPath)
            ? require $configPath
            : $this->getDefaultConfig();
    }

    private function getDefaultConfig(): array
    {
        return [
            'format' => 'json',
            'level' => 'info',
            'output' => '/var/log/librenms/weathermapng.log',
            'use_stdout' => false,
            'rotation' => [
                'enabled' => true,
                'max_files' => 7,
                'max_size' => '10M',
            ],
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
        ];
    }

    private function initializeLogger(): void
    {
        $this->logger = new MonologLogger('weathermapng');

        // Add processors for structured fields
        if ($this->config['structured_fields']['hostname'] ?? true) {
            $this->logger->pushProcessor(new HostnameProcessor());
        }
        if ($this->config['structured_fields']['process_id'] ?? true) {
            $this->logger->pushProcessor(new ProcessIdProcessor());
        }
        if ($this->config['structured_fields']['memory_usage'] ?? true) {
            $this->logger->pushProcessor(new MemoryUsageProcessor());
        }

        // Add execution time processor
        if ($this->config['structured_fields']['execution_time'] ?? true) {
            $this->logger->pushProcessor(function ($record) {
                $record['extra']['execution_time'] = round(microtime(true) - $this->startTime, 3);
                return $record;
            });
        }

        // Set up handler
        $handler = $this->createHandler();

        // Set formatter based on config
        if ($this->config['format'] === 'json') {
            $handler->setFormatter(new JsonFormatter());
        } else {
            $format = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
            $handler->setFormatter(new LineFormatter($format));
        }

        $this->logger->pushHandler($handler);
    }

    private function createHandler(): object
    {
        $output = $this->config['output'];

        // Use stdout for Docker containers
        if ($this->config['use_stdout'] || $output === '/dev/stdout') {
            return new StreamHandler('php://stdout', $this->getLogLevel());
        }

        // Use rotating file handler if rotation is enabled
        if ($this->config['rotation']['enabled'] ?? true) {
            return new RotatingFileHandler(
                $output,
                $this->config['rotation']['max_files'] ?? 7,
                $this->getLogLevel()
            );
        }

        // Default to stream handler
        return new StreamHandler($output, $this->getLogLevel());
    }

    private function getLogLevel(): int
    {
        $levels = [
            'debug' => MonologLogger::DEBUG,
            'info' => MonologLogger::INFO,
            'warning' => MonologLogger::WARNING,
            'error' => MonologLogger::ERROR,
            'critical' => MonologLogger::CRITICAL,
        ];

        return $levels[$this->config['level']] ?? MonologLogger::INFO;
    }

    // Public logging methods
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $this->sanitizeContext($context));
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $this->sanitizeContext($context));
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $this->sanitizeContext($context));
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $this->sanitizeContext($context));
    }

    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $this->sanitizeContext($context));
    }

    // Performance logging
    public function logPerformance(string $operation, float $duration, array $context = []): void
    {
        $context['duration_ms'] = round($duration * 1000, 2);
        $context['operation'] = $operation;

        if ($context['duration_ms'] > ($this->config['performance']['slow_query_threshold'] ?? 1000)) {
            $this->warning("Slow operation detected: $operation", $context);
        } else {
            $this->debug("Performance: $operation", $context);
        }
    }

    // Security logging
    public function logSecurity(string $event, array $context = []): void
    {
        $context['security_event'] = $event;
        $context['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $this->warning("Security event: $event", $context);
    }

    // Sanitize sensitive data from context
    private function sanitizeContext(array $context): array
    {
        if (!($this->config['security']['mask_sensitive_data'] ?? true)) {
            return $context;
        }

        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'credential'];

        array_walk_recursive($context, function (&$value, $key) use ($sensitiveKeys) {
            foreach ($sensitiveKeys as $sensitive) {
                if (is_string($key) && stripos($key, $sensitive) !== false) {
                    $value = '***REDACTED***';
                    break;
                }
            }
        });

        return $context;
    }

    // Helper for timing operations
    public function timeOperation(callable $operation, string $name, array $context = []): mixed
    {
        $start = microtime(true);

        try {
            $result = $operation();
            $duration = microtime(true) - $start;
            $this->logPerformance($name, $duration, array_merge($context, ['status' => 'success']));
            return $result;
        } catch (\Exception $e) {
            $duration = microtime(true) - $start;
            $this->logPerformance($name, $duration, array_merge($context, [
                'status' => 'error',
                'error' => $e->getMessage()
            ]));
            throw $e;
        }
    }
}
