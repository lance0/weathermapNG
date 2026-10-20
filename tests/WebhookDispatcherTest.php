<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\WebhookDispatcher;

class WebhookDispatcherTest extends TestCase
{
    private function makeDispatcher(): WebhookDispatcher
    {
        return new WebhookDispatcher();
    }

    public function test_configured_urls_parses_line_form(): void
    {
        config(['weathermapng.webhook_urls' => "https://a.example/hook\nhttps://b.example/hook\n\n  \n"]);
        config(['weathermapng.webhooks.urls' => []]);

        $urls = $this->makeDispatcher()->configuredUrls();

        $this->assertSame(['https://a.example/hook', 'https://b.example/hook'], $urls);
    }

    public function test_configured_urls_merges_array_form(): void
    {
        config(['weathermapng.webhook_urls' => 'https://a.example/hook']);
        config(['weathermapng.webhooks.urls' => ['https://b.example/hook']]);

        $urls = $this->makeDispatcher()->configuredUrls();

        $this->assertSame(['https://a.example/hook', 'https://b.example/hook'], $urls);
    }

    public function test_configured_urls_empty_when_unset(): void
    {
        config(['weathermapng.webhook_urls' => null]);
        config(['weathermapng.webhooks.urls' => null]);

        $this->assertSame([], $this->makeDispatcher()->configuredUrls());
    }

    public function test_signature_is_hmac_over_timestamp_dot_body(): void
    {
        config(['weathermapng.webhooks.secret' => 's3cret']);
        $body = '{"event":"map.created"}';

        $signature = $this->makeDispatcher()->buildSignature($body);

        $this->assertNotNull($signature);
        $this->assertMatchesRegularExpression('/^t=\d+,v1=[0-9a-f]{64}$/', $signature);

        // Verify a receiver can reproduce it: split t= and v1=, recompute HMAC
        [$tPart, $vPart] = explode(',', $signature);
        $timestamp = substr($tPart, 2);
        $expected = 'v1=' . hash_hmac('sha256', $timestamp . '.' . $body, 's3cret');
        $this->assertSame($expected, $vPart);
    }

    public function test_signature_null_without_secret(): void
    {
        config(['weathermapng.webhooks.secret' => null]);

        $this->assertNull($this->makeDispatcher()->buildSignature('{}'));
    }

    public function test_dispatch_is_noop_without_urls(): void
    {
        config(['weathermapng.webhook_urls' => '']);
        config(['weathermapng.webhooks.urls' => []]);

        // No URLs configured — dispatch must return without touching cURL.
        $this->expectNotToPerformAssertions();
        $this->makeDispatcher()->dispatch('map.created', ['map_id' => 1, 'map_name' => 'x']);
    }

    public function test_map_service_wires_webhook_on_create(): void
    {
        // MapService takes an optional WebhookDispatcher; verify the wiring
        // contract — createMap fires map.created when a dispatcher is present.
        $content = file_get_contents(__DIR__ . '/../src/Services/MapService.php');
        $this->assertStringContainsString('private readonly ?WebhookDispatcher $webhooks = null', $content);
        $this->assertStringContainsString("notify('map.created'", $content);
        $this->assertStringContainsString("notify('map.updated'", $content);
        $this->assertStringContainsString("notify('map.deleted'", $content);
    }

    public function test_version_service_fires_version_saved(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Services/MapVersionService.php');
        $this->assertStringContainsString("dispatch('map.version_saved'", $content);
    }
}
