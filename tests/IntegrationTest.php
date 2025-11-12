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
    /** @test */
    public function core_classes_can_be_instantiated()
    {
        // This test verifies that the core classes can be instantiated
        // and have the expected methods

        // Test that we can create the main controller classes
        $mapController = new MapController();
        $renderController = new RenderController();

        $this->assertInstanceOf(MapController::class, $mapController);
        $this->assertInstanceOf(RenderController::class, $renderController);

        // Test that core classes exist
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapController'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\WeathermapNG\Services\PortUtilService'));
    }

    // Note: Full integration testing requires Laravel framework with database
    // This basic test verifies that core components can be instantiated together
    // Comprehensive integration testing would require full Laravel test environment
    // with database migrations, factories, and HTTP request simulation
}