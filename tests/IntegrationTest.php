<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController;
use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    public function test_controllers_exist()
    {
        $this->assertTrue(class_exists(MapController::class));
        $this->assertTrue(class_exists(RenderController::class));
    }

    public function test_services_exist()
    {
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\PortUtilService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\AlertService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\LinkDataService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\MapVersionService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\MapService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\NodeService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\LinkService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\AutoDiscoveryService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\RrdDataService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\NodeDataService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\DeviceDataService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\LinkDataService'));
    }

    public function test_render_controller_with_dependencies()
    {
        $nodeDataService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\NodeDataService::class
        );

        $mapService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\MapService::class
        );

        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController($nodeDataService, $mapService);

        $this->assertInstanceOf(\LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController::class, $controller);
    }
}
