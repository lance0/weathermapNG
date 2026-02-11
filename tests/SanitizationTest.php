<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for FormRequest sanitize() methods.
 * These are tested as standalone functions since FormRequest
 * requires Laravel, but the sanitize logic is pure.
 */
class SanitizationTest extends TestCase
{
    // --- CreateMapRequest-style sanitization ---

    private function sanitizeMapData(array $data): array
    {
        $data['name'] = strip_tags($data['name']);
        if (isset($data['title'])) {
            $data['title'] = strip_tags($data['title']);
        }
        return $data;
    }

    public function test_map_name_strips_html_tags(): void
    {
        $data = $this->sanitizeMapData(['name' => '<script>alert("xss")</script>my-map']);
        $this->assertEquals('alert("xss")my-map', $data['name']);
    }

    public function test_map_title_strips_html_tags(): void
    {
        $data = $this->sanitizeMapData(['name' => 'test', 'title' => '<b>Bold</b> Title']);
        $this->assertEquals('Bold Title', $data['title']);
    }

    public function test_map_sanitize_preserves_clean_input(): void
    {
        $data = $this->sanitizeMapData(['name' => 'my-network-map', 'title' => 'Network Map']);
        $this->assertEquals('my-network-map', $data['name']);
        $this->assertEquals('Network Map', $data['title']);
    }

    public function test_map_sanitize_without_title(): void
    {
        $data = $this->sanitizeMapData(['name' => 'test-map']);
        $this->assertEquals('test-map', $data['name']);
        $this->assertArrayNotHasKey('title', $data);
    }

    // --- CreateNodeRequest-style sanitization ---

    private function sanitizeNodeData(array $data): array
    {
        $data['label'] = strip_tags($data['label']);
        $data['label'] = htmlspecialchars($data['label'], ENT_QUOTES, 'UTF-8');
        return $data;
    }

    public function test_node_label_strips_html(): void
    {
        $data = $this->sanitizeNodeData(['label' => '<img src=x onerror=alert(1)>Router-1']);
        $this->assertEquals('Router-1', $data['label']);
    }

    public function test_node_label_escapes_special_chars(): void
    {
        $data = $this->sanitizeNodeData(['label' => 'Router "Core" & <Main>']);
        // strip_tags removes <Main>, then htmlspecialchars encodes quotes and ampersand
        $this->assertEquals('Router &quot;Core&quot; &amp; ', $data['label']);
    }

    public function test_node_label_preserves_normal_text(): void
    {
        $data = $this->sanitizeNodeData(['label' => 'Core-Router-01']);
        $this->assertEquals('Core-Router-01', $data['label']);
    }

    public function test_node_label_handles_unicode(): void
    {
        $data = $this->sanitizeNodeData(['label' => 'Routeur-Réseau']);
        $this->assertEquals('Routeur-Réseau', $data['label']);
    }

    // --- Edge cases ---

    public function test_empty_string_sanitization(): void
    {
        $map = $this->sanitizeMapData(['name' => '']);
        $this->assertEquals('', $map['name']);

        $node = $this->sanitizeNodeData(['label' => '']);
        $this->assertEquals('', $node['label']);
    }

    public function test_nested_tags_fully_stripped(): void
    {
        $data = $this->sanitizeMapData(['name' => '<div><span><b>clean</b></span></div>']);
        $this->assertEquals('clean', $data['name']);
    }

    public function test_script_injection_fully_stripped(): void
    {
        $data = $this->sanitizeNodeData(['label' => '<script>document.cookie</script>Node']);
        $this->assertEquals('document.cookieNode', $data['label']);
    }
}
