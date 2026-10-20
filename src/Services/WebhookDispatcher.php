<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\Log;

/**
 * Dispatches map lifecycle events to configured webhook URLs.
 *
 * Payload: {event, map_id, map_name, user, timestamp}
 * Each request carries an HMAC-SHA256 signature header (X-WeathermapNG-Signature)
 * computed over the raw JSON body with the configured secret, so receivers
 * can verify authenticity. Delivery is best-effort: failures are logged and
 * never block the map mutation that triggered them.
 *
 * Uses cURL directly (PHP core) rather than the Laravel Http facade, which
 * is not part of the plugin's standalone dependency set.
 */
class WebhookDispatcher
{
    public const EVENTS = ['map.created', 'map.updated', 'map.deleted', 'map.version_saved'];

    /**
     * @param  string  $event  one of self::EVENTS
     * @param  array{map_id: int, map_name: string}  $map
     */
    public function dispatch(string $event, array $map): void
    {
        if (!in_array($event, self::EVENTS, true)) {
            Log::warning("WeathermapNG: refusing to dispatch unknown webhook event '{$event}'");
            return;
        }

        $urls = $this->configuredUrls();
        if (empty($urls)) {
            return; // webhooks not configured — no-op
        }

        $payload = [
            'event' => $event,
            'map_id' => $map['map_id'],
            'map_name' => $map['map_name'],
            'user' => $this->currentUsername(),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        foreach ($urls as $url) {
            $this->post($url, $body);
        }
    }

    /** Visible for testing. */
    public function buildSignature(string $body): ?string
    {
        $secret = config('weathermapng.webhooks.secret');

        if (empty($secret)) {
            return null; // no secret configured — unsigned
        }

        $timestamp = (string) time();
        $signed = $timestamp . '.' . $body;

        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $signed, $secret);
    }

    private function post(string $url, string $body): void
    {
        if (!function_exists('curl_init')) {
            Log::warning('WeathermapNG: webhook delivery skipped — cURL extension not available');
            return;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }

        $headers = ['Content-Type: application/json'];
        $signature = $this->buildSignature($body);
        if ($signature !== null) {
            $headers[] = 'X-WeathermapNG-Signature: ' . $signature;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        try {
            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

            if ($response === false || $status < 200 || $status >= 300) {
                Log::warning('WeathermapNG: webhook delivery failed', [
                    'url' => $url,
                    'status' => $status ?: 0,
                    'error' => curl_error($ch) ?: null,
                ]);
            }
        } finally {
            curl_close($ch);
        }
    }

    /**
     * URLs from config, merging both shapes: webhook_urls is the
     * settings-form string (one URL per line, also settable via
     * WEATHERMAPNG_WEBHOOK_URLS), webhooks.urls is the config-file
     * array form.
     */
    public function configuredUrls(): array
    {
        $urls = [];

        $lineForm = config('weathermapng.webhook_urls');
        if (is_string($lineForm) && $lineForm !== '') {
            $urls = preg_split('/\r\n|\r|\n/', $lineForm) ?: [];
        }

        $arrayForm = config('weathermapng.webhooks.urls');
        if (is_array($arrayForm)) {
            $urls = array_merge($urls, $arrayForm);
        }

        return array_values(array_filter(array_map('trim', $urls), fn ($u) => $u !== ''));
    }

    private function currentUsername(): ?string
    {
        try {
            return auth()->user()?->username;
        } catch (\Throwable $e) {
            return null; // CLI context (poller, artisan) has no session user
        }
    }
}
