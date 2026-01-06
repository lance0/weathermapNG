<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapController;
use PHPUnit\Framework\TestCase;

class MapControllerTest extends TestCase
{
    protected MapController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $mapService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\MapService::class
        );

        $nodeService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\NodeService::class
        );

        $linkService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\LinkService::class
        );

        $autoDiscoveryService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\AutoDiscoveryService::class
        );

        $this->controller = new MapController(
            $mapService,
            $nodeService,
            $linkService,
            $autoDiscoveryService
        );
    }

    public function test_controller_can_be_instantiated()
    {
        $this->assertInstanceOf(MapController::class, $this->controller);
    }

    public function test_controller_has_required_methods()
    {
        $this->assertTrue(method_exists($this->controller, 'index'));
        $this->assertTrue(method_exists($this->controller, 'create'));
        $this->assertTrue(method_exists($this->controller, 'update'));
        $this->assertTrue(method_exists($this->controller, 'destroy'));
        $this->assertTrue(method_exists($this->controller, 'createNode'));
        $this->assertTrue(method_exists($this->controller, 'createLink'));
        $this->assertTrue(method_exists($this->controller, 'save'));
        $this->assertTrue(method_exists($this->controller, 'autoDiscover'));
    }

    public function test_services_are_injected()
    {
        $mapService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\MapService::class
        );

        $nodeService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\NodeService::class
        );

        $linkService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\LinkService::class
        );

        $autoDiscoveryService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\AutoDiscoveryService::class
        );

        $controller = new MapController(
            $mapService,
            $nodeService,
            $linkService,
            $autoDiscoveryService
        );

        $this->assertInstanceOf(MapController::class, $controller);
    }
}
