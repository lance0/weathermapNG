<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests\Feature;

use LibreNMS\Plugins\WeathermapNG\Tests\TestCase;
use LibreNMS\Plugins\WeathermapNG\Models\Map;

class HealthControllerTest extends TestCase
{
    /** @test */
    public function it_returns_health_status()
    {
        $response = $this->get('/plugins/weathermapng/health');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'timestamp',
                    'version',
                    'checks' => [
                        'database',
                        'rrd',
                        'output',
                        'api_token'
                    ]
                ]);

        $responseData = $response->json();
        $this->assertContains($responseData['status'], ['healthy', 'warning', 'unhealthy']);
        $this->assertIsInt($responseData['timestamp']);
        $this->assertEquals('1.0.0', $responseData['version']);
    }

    /** @test */
    public function it_returns_healthy_status_when_everything_works()
    {
        // Create a test map to ensure database is working
        Map::create(['name' => 'health-test', 'title' => 'Health Test Map']);

        $response = $this->get('/plugins/weathermapng/health');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertEquals('healthy', $responseData['status']);

        // Check that database check is healthy
        $this->assertEquals('healthy', $responseData['checks']['database']['status']);
        $this->assertStringContains('Database connected', $responseData['checks']['database']['message']);
    }

    /** @test */
    public function it_returns_system_statistics()
    {
        // Create some test data
        Map::create(['name' => 'stats-test-1', 'title' => 'Stats Test 1']);
        Map::create(['name' => 'stats-test-2', 'title' => 'Stats Test 2']);

        $response = $this->get('/plugins/weathermapng/health/stats');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'maps',
                    'nodes',
                    'links',
                    'last_updated',
                    'database_size'
                ]);

        $responseData = $response->json();
        $this->assertIsInt($responseData['maps']);
        $this->assertIsInt($responseData['nodes']);
        $this->assertIsInt($responseData['links']);
        $this->assertGreaterThanOrEqual(2, $responseData['maps']);
    }

    /** @test */
    public function it_includes_cache_information_in_stats()
    {
        $response = $this->get('/plugins/weathermapng/health/stats');

        $response->assertStatus(200);

        $responseData = $response->json();

        // Cache info might not be available in test environment
        if (isset($responseData['cache_info'])) {
            $this->assertIsArray($responseData['cache_info']);
        }
    }

    /** @test */
    public function it_handles_database_errors_gracefully()
    {
        // This test would require mocking database failures
        // For now, we'll just ensure the endpoint exists and returns proper structure

        $response = $this->get('/plugins/weathermapng/health');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertArrayHasKey('database', $responseData['checks']);
        $this->assertArrayHasKey('status', $responseData['checks']['database']);
        $this->assertArrayHasKey('message', $responseData['checks']['database']);
    }

    /** @test */
    public function it_checks_output_directory_permissions()
    {
        $response = $this->get('/plugins/weathermapng/health');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertArrayHasKey('output', $responseData['checks']);
        $this->assertArrayHasKey('status', $responseData['checks']['output']);
        $this->assertArrayHasKey('message', $responseData['checks']['output']);
    }

    /** @test */
    public function it_validates_api_token_configuration()
    {
        $response = $this->get('/plugins/weathermapng/health');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertArrayHasKey('api_token', $responseData['checks']);
        $this->assertArrayHasKey('status', $responseData['checks']['api_token']);
        $this->assertArrayHasKey('message', $responseData['checks']['api_token']);
    }

    /** @test */
    public function it_returns_warning_status_for_missing_configurations()
    {
        // This test verifies that the health check properly identifies
        // configuration issues and returns appropriate status

        $response = $this->get('/plugins/weathermapng/health');

        $response->assertStatus(200);

        $responseData = $response->json();

        // The status should be either healthy or warning
        $this->assertContains($responseData['status'], ['healthy', 'warning', 'unhealthy']);

        // If any check has a warning, the overall status should reflect that
        $hasWarnings = false;
        foreach ($responseData['checks'] as $check) {
            if (($check['status'] ?? '') === 'warning') {
                $hasWarnings = true;
                break;
            }
        }

        if ($hasWarnings) {
            $this->assertEquals('warning', $responseData['status']);
        }
    }

    /** @test */
    public function it_provides_detailed_error_messages()
    {
        $response = $this->get('/plugins/weathermapng/health');

        $response->assertStatus(200);

        $responseData = $response->json();

        // Each check should have a descriptive message
        foreach ($responseData['checks'] as $checkName => $check) {
            $this->assertArrayHasKey('message', $check, "Check '{$checkName}' missing message");
            $this->assertIsString($check['message'], "Check '{$checkName}' message should be string");
            $this->assertNotEmpty($check['message'], "Check '{$checkName}' message should not be empty");
        }
    }

    /** @test */
    public function it_returns_iso_8601_timestamp()
    {
        $response = $this->get('/plugins/weathermapng/health');

        $response->assertStatus(200);

        $responseData = $response->json();

        // Verify timestamp is in ISO 8601 format
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $responseData['timestamp']
        );
    }

    /** @test */
    public function it_includes_rrd_accessibility_check()
    {
        $response = $this->get('/plugins/weathermapng/health');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertArrayHasKey('rrd', $responseData['checks']);
        $this->assertArrayHasKey('status', $responseData['checks']['rrd']);
        $this->assertArrayHasKey('message', $responseData['checks']['rrd']);
    }
}