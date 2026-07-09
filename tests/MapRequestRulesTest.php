<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

class MapRequestRulesTest extends TestCase
{
    public function test_save_map_request_allows_name_field(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Requests/SaveMapRequest.php');
        $this->assertStringContainsString("'name' => 'nullable|string|max:255'", $content);
    }

    public function test_save_map_request_sanitizes_name(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Requests/SaveMapRequest.php');
        $this->assertStringContainsString("foreach (['title', 'name'] as \$key)", $content);
        $this->assertStringContainsString('strip_tags(trim($data[$key]))', $content);
    }

    public function test_map_service_updates_name_when_present(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Services/MapService.php');
        $this->assertStringContainsString("array_key_exists('name', \$data) && \$data['name'] !== null && \$data['name'] !== ''", $content);
    }

    public function test_save_map_request_allows_partial_default_styles(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Requests/SaveMapRequest.php');
        // Laravel array:foo restricts allowed keys and does NOT require them all.
        $this->assertStringContainsString("'options.default_node_style' => 'nullable|array:color,label_color'", $content);
        $this->assertStringContainsString("'options.default_link_style' => 'nullable|array:color,width,via_style'", $content);
    }

    public function test_save_map_request_allows_link_style_color_and_width(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Requests/SaveMapRequest.php');
        $this->assertStringContainsString("'links.*.style' => 'nullable|array:via_style,via_points,color,width'", $content);
        $this->assertStringContainsString("'links.*.style.color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{6}$/'", $content);
        $this->assertStringContainsString("'links.*.style.width' => 'nullable|numeric|min:0.5|max:20'", $content);
    }

    public function test_map_service_has_no_numeric_fallback_for_node_ids(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Services/MapService.php');
        $this->assertStringNotContainsString('is_numeric($clientId)', $content);
        $this->assertStringContainsString('return $nodeIdMap[$clientId] ?? null;', $content);
    }
}
