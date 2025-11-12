<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\Http\Controllers\LookupController;
use PHPUnit\Framework\TestCase;

class LookupControllerTest extends TestCase
{
    protected LookupController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new LookupController();
    }

    /** @test */
    public function controller_can_be_instantiated()
    {
        $this->assertInstanceOf(LookupController::class, $this->controller);
    }

    /** @test */
    public function controller_has_required_methods()
    {
        $this->assertTrue(method_exists($this->controller, 'devices'));
        $this->assertTrue(method_exists($this->controller, 'ports'));
    }

    // Note: Full integration tests for controllers require Laravel framework
    // These basic tests verify the controller structure and methods exist
    // Comprehensive testing would require Laravel test environment with mocked services
}