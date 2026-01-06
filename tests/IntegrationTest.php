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
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\MapDataBuilder'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\SseStreamService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\MapService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\NodeService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\LinkService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\AutoDiscoveryService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\RrdDataService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\SnmpDataService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\NodeDataService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\DeviceDataService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\LinkDataService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\DeviceMetricsService'));
    }

    public function test_render_controller_with_dependencies()
    {
        $nodeDataService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\NodeDataService::class
        );

        $sseStreamService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\SseStreamService::class
        );

        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController($nodeDataService, $sseStreamService);

        $this->assertInstanceOf(\LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController::class, $controller);
    }
}
