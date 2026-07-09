<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

class TagsFeatureTest extends TestCase
{
    private string $mapModel;
    private string $saveRequest;
    private string $mapService;
    private string $indexView;
    private string $editorView;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapModel = file_get_contents(__DIR__ . '/../src/Models/Map.php');
        $this->saveRequest = file_get_contents(__DIR__ . '/../src/Http/Requests/SaveMapRequest.php');
        $this->mapService = file_get_contents(__DIR__ . '/../src/Services/MapService.php');
        $this->indexView = file_get_contents(__DIR__ . '/../resources/views/index.blade.php');
        $this->editorView = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
    }

    public function test_map_model_has_tags_accessor(): void
    {
        $this->assertStringContainsString('public function getTagsAttribute()', $this->mapModel);
        $this->assertStringContainsString("data_get(\$this->options, 'tags', []);", $this->mapModel);
        $this->assertStringContainsString('array_unique', $this->mapModel);
        $this->assertStringContainsString('strtolower', $this->mapModel);
    }

    public function test_save_request_validates_tags(): void
    {
        $this->assertStringContainsString("'options.tags' => 'nullable|array|max:50'", $this->saveRequest);
        $this->assertStringContainsString("'options.tags.*' => 'nullable|string|max:50|regex:/^[a-z0-9_-]+$/i'", $this->saveRequest);
    }

    public function test_save_request_sanitizes_tags(): void
    {
        $this->assertStringContainsString("if (!empty(\$data['options']['tags']) && is_array(\$data['options']['tags']))", $this->saveRequest);
        $this->assertStringContainsString('array_unique', $this->saveRequest);
        $this->assertStringContainsString('strtolower', $this->saveRequest);
    }

    public function test_map_service_uses_merge_map_options(): void
    {
        $this->assertStringContainsString('$this->mergeMapOptions($map->options ?? [], $incomingOptions);', $this->mapService);
    }

    public function test_index_view_has_tag_filter_and_chips(): void
    {
        $this->assertStringContainsString("id=\"map-tag-filter\"", $this->indexView);
        $this->assertStringContainsString("data-tags=\"{{ json_encode(\$map->tags) }}\"", $this->indexView);
        $this->assertStringContainsString("class=\"map-tags\"", $this->indexView);
        $this->assertStringContainsString("class=\"map-tag\"", $this->indexView);
        $this->assertStringContainsString("getCardTags(card)", $this->indexView);
    }

    public function test_index_view_has_onboarding_links(): void
    {
        $this->assertStringContainsString('wmng-onboarding-actions', $this->indexView);
        $this->assertStringContainsString("href=\"{{ url('plugin/WeathermapNG/templates') }}\"", $this->indexView);
        $this->assertStringContainsString("href=\"{{ url('plugin/WeathermapNG/diagnostics') }}\"", $this->indexView);
        $this->assertStringContainsString("data-target=\"#importMapModal\"", $this->indexView);
    }

    public function test_editor_view_has_tags_input(): void
    {
        $this->assertStringContainsString("id=\"map-tags\"", $this->editorView);
        $this->assertStringContainsString('parseMapTags', $this->editorView);
        $this->assertStringContainsString('tags: parseMapTags', $this->editorView);
    }
}
