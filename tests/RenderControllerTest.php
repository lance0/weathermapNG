<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController;
use PHPUnit\Framework\TestCase;

class RenderControllerTest extends TestCase
{
    protected RenderController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $mapDataBuilder = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\MapDataBuilder::class
        );

        $sseStreamService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\SseStreamService::class
        );

        $this->controller = new RenderController($mapDataBuilder, $sseStreamService);
    }

    public function test_controller_can_be_instantiated()
    {
        $this->assertInstanceOf(RenderController::class, $this->controller);
    }

    public function test_controller_has_required_methods()
    {
        $this->assertTrue(method_exists($this->controller, 'json'));
        $this->assertTrue(method_exists($this->controller, 'live'));
        $this->assertTrue(method_exists($this->controller, 'embed'));
        $this->assertTrue(method_exists($this->controller, 'export'));
        $this->assertTrue(method_exists($this->controller, 'import'));
        $this->assertTrue(method_exists($this->controller, 'sse'));
    }

    public function test_services_are_injected()
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
