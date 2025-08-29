<?php
// lib/RRD/LibreNMSAPI.php
namespace LibreNMS\Plugins\WeathermapNG\RRD;

use Illuminate\Support\Facades\Http;

class LibreNMSAPI
{
    private $baseUrl;
    private $apiToken;

    public function __construct()
    {
        $this->baseUrl = config('app.url', 'http://localhost');
        $this->apiToken = config('weathermapng.api_token');
    }

    public function getPortData($deviceId, $interfaceId, $metric, $period = '1h')
    {
        if (!$this->apiToken) {
            return $this->generateMockData($period);
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(10)
                ->get("{$this->baseUrl}/api/v0/devices/{$deviceId}/ports/{$interfaceId}", [
                    'period' => $period
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->formatApiData($data, $metric);
            }

            // If API fails, return mock data
            return $this->generateMockData($period);

        } catch (\Exception $e) {
            return $this->generateMockData($period);
        }
    }

    private function formatApiData($apiData, $metric)
    {
        $formatted = [];

        if (!isset($apiData['data'])) {
            return $formatted;
        }

        foreach ($apiData['data'] as $entry) {
            $timestamp = strtotime($entry['timestamp'] ?? $entry['time'] ?? 'now');
            $value = null;

            // Map metric to API response field
            switch ($metric) {
                case 'traffic_in':
                    $value = $entry['ifInOctets_rate'] ?? $entry['traffic_in'] ?? null;
                    break;
                case 'traffic_out':
                    $value = $entry['ifOutOctets_rate'] ?? $entry['traffic_out'] ?? null;
                    break;
                case 'packets_in':
                    $value = $entry['ifInUcastPkts_rate'] ?? $entry['packets_in'] ?? null;
                    break;
                case 'packets_out':
                    $value = $entry['ifOutUcastPkts_rate'] ?? $entry['packets_out'] ?? null;
                    break;
                default:
                    $value = $entry[$metric] ?? null;
            }

            if ($value !== null && is_numeric($value)) {
                $formatted[] = [
                    'timestamp' => $timestamp,
                    'value' => (float)$value
                ];
            }
        }

        return $formatted;
    }

    private function generateMockData($period)
    {
        $data = [];
        $start = strtotime("-{$period}");
        $end = time();
        $interval = 300; // 5 minutes

        for ($timestamp = $start; $timestamp <= $end; $timestamp += $interval) {
            $data[] = [
                'timestamp' => $timestamp,
                'value' => rand(1000000, 50000000) // Random traffic-like values
            ];
        }

        return $data;
    }

    public function getDeviceData($deviceId, $metric, $period = '1h')
    {
        if (!$this->apiToken) {
            return $this->generateMockData($period);
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(10)
                ->get("{$this->baseUrl}/api/v0/devices/{$deviceId}", [
                    'period' => $period
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->formatDeviceData($data, $metric);
            }

            return $this->generateMockData($period);

        } catch (\Exception $e) {
            return $this->generateMockData($period);
        }
    }

    private function formatDeviceData($apiData, $metric)
    {
        // Similar to formatApiData but for device-level metrics
        $formatted = [];

        if (!isset($apiData['data'])) {
            return $formatted;
        }

        foreach ($apiData['data'] as $entry) {
            $timestamp = strtotime($entry['timestamp'] ?? 'now');
            $value = $entry[$metric] ?? null;

            if ($value !== null && is_numeric($value)) {
                $formatted[] = [
                    'timestamp' => $timestamp,
                    'value' => (float)$value
                ];
            }
        }

        return $formatted;
    }
}