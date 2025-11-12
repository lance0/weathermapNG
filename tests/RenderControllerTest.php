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
        $this->controller = new RenderController();
    }

    /** @test */
    public function controller_can_be_instantiated()
    {
        $this->assertInstanceOf(RenderController::class, $this->controller);
    }

    /** @test */
    public function controller_has_required_methods()
    {
        $this->assertTrue(method_exists($this->controller, 'json'));
        $this->assertTrue(method_exists($this->controller, 'live'));
        $this->assertTrue(method_exists($this->controller, 'embed'));
        $this->assertTrue(method_exists($this->controller, 'export'));
        $this->assertTrue(method_exists($this->controller, 'import'));
        $this->assertTrue(method_exists($this->controller, 'sse'));
    }

    // Note: Full integration tests for controllers require Laravel framework
    // These basic tests verify the controller structure and methods exist
    // Comprehensive testing would require Laravel test environment with database
}