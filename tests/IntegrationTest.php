<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController;
use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    public function test_core_classes_exist()
    {
        $this->assertTrue(class_exists(Map::class));
        $this->assertTrue(class_exists(Node::class));
        $this->assertTrue(class_exists(Link::class));
    }

    public function test_controllers_exist()
    {
        $this->assertTrue(class_exists(MapController::class));
        $this->assertTrue(class_exists(RenderController::class));
    }

    public function test_services_exist()
    {
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\PortUtilService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\AlertService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\MapDataBuilder'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\SseStreamService'));
    }

    public function test_render_controller_with_dependencies()
    {
        $mapDataBuilder = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\MapDataBuilder::class
        );

        $sseStreamService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\SseStreamService::class
        );

        $controller = new RenderController($mapDataBuilder, $sseStreamService);

        $this->assertInstanceOf(RenderController::class, $controller);
    }
}
