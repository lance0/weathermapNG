<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseStreamService
{
    private $nodeDataService;

    public function __construct(NodeDataService $nodeDataService)
    {
        $this->nodeDataService = $nodeDataService;
    }

    public function stream(Map $map, int $interval, int $maxSeconds): StreamedResponse
    {
        return \response()->stream(
            function () use ($map, $interval, $maxSeconds) {
                $this->configureOutputBuffering();
                $this->streamLoop($map, $interval, $maxSeconds);
            },
            200,
            $this->getResponseHeaders()
        );
    }

    private function configureOutputBuffering(): void
    {
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        @ob_implicit_flush(1);
    }

    private function getResponseHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ];
    }

    private function streamLoop(Map $map, int $interval, int $maxSeconds): void
    {
        $start = time();

        while (true) {
            $payload = $this->buildSsePayload($map);
            $this->emitSseEvent($payload);

            if ($this->shouldStopStreaming($start, $maxSeconds)) {
                break;
            }

            sleep($interval);
        }
    }

    private function buildSsePayload(Map $map): array
    {
        return [
            'ts' => time(),
            'links' => $this->nodeDataService->buildLinkData($map),
            'nodes' => $this->nodeDataService->buildNodeData($map),
            'alerts' => $this->nodeDataService->buildAlertData(),
        ];
    }

    private function emitSseEvent(array $payload): void
    {
        echo 'data: ' . json_encode($payload) . "\n\n";
        @ob_flush();
        @flush();
    }

    private function shouldStopStreaming(int $startTime, int $maxSeconds): bool
    {
        return connection_aborted() || (time() - $startTime) >= $maxSeconds;
    }
}
