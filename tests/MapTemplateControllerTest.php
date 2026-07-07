<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

class MapTemplateControllerTest extends TestCase
{
    public function test_createFromTemplate_imports_required_classes(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/MapTemplateController.php');

        $this->assertStringContainsString('use Illuminate\Support\Facades\DB;', $content);
        $this->assertStringContainsString('use LibreNMS\Plugins\WeathermapNG\Models\Map;', $content);
        $this->assertStringContainsString('use LibreNMS\Plugins\WeathermapNG\Models\Node;', $content);
        $this->assertStringContainsString('use LibreNMS\Plugins\WeathermapNG\Models\Link;', $content);
    }

    public function test_createFromTemplate_wraps_creation_in_transaction(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/MapTemplateController.php');

        $this->assertStringContainsString('DB::transaction(', $content);
    }

    public function test_createFromTemplate_validates_config_before_creation(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/MapTemplateController.php');

        $this->assertStringContainsString('decodeTemplateConfig', $content);
        $this->assertStringContainsString('validateTemplateConfig', $content);
        $this->assertStringContainsString('must contain a "default_nodes" array', $content);
        $this->assertStringContainsString('must contain a "default_links" array', $content);
        $this->assertStringContainsString('must contain src_node_idx and dst_node_idx', $content);
    }

    public function test_createFromTemplate_returns_400_for_invalid_config(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/MapTemplateController.php');

        $this->assertStringContainsString("], 400);", $content);
        $this->assertStringContainsString('Template config is not valid JSON', $content);
    }

    public function test_createFromTemplate_stores_width_height_in_options(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/MapTemplateController.php');

        // Must NOT pass width/height as top-level Map::create keys
        $this->assertStringNotContainsString("'width' => \$template->width,", $content);
        $this->assertStringNotContainsString("'height' => \$template->height,", $content);

        // Must store them inside options
        $this->assertStringContainsString("\$options['width'] = \$template->width;", $content);
        $this->assertStringContainsString("\$options['height'] = \$template->height;", $content);

        // Map::create must only use fillable keys
        $this->assertStringContainsString("'options' => \$options", $content);
    }
}
