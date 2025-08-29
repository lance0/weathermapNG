<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we're using the test database
        if (!config('database.connections.testing')) {
            $this->markTestSkipped('Testing database not configured');
        }

        // Set up test-specific configuration
        config([
            'weathermapng.cache_ttl' => 0, // Disable caching in tests
            'weathermapng.enable_local_rrd' => false, // Disable RRD in tests
            'weathermapng.enable_api_fallback' => false, // Disable API in tests
        ]);

        // Clean up any leftover test data
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    protected function cleanupTestData(): void
    {
        try {
            DB::table('wmng_links')->truncate();
            DB::table('wmng_nodes')->truncate();
            DB::table('wmng_maps')->truncate();
        } catch (\Exception $e) {
            // Ignore if tables don't exist yet
        }
    }

    /**
     * Create a test map with optional nodes and links
     */
    protected function createTestMap(array $attributes = [], int $nodeCount = 0, int $linkCount = 0)
    {
        $map = \LibreNMS\Plugins\WeathermapNG\Models\Map::create(array_merge([
            'name' => 'test_map_' . uniqid(),
            'title' => 'Test Map',
            'options' => ['width' => 800, 'height' => 600]
        ], $attributes));

        if ($nodeCount > 0) {
            for ($i = 0; $i < $nodeCount; $i++) {
                $map->nodes()->create([
                    'label' => "Node {$i}",
                    'x' => $i * 100,
                    'y' => $i * 50,
                    'device_id' => $i + 1
                ]);
            }
        }

        if ($linkCount > 0 && $nodeCount >= 2) {
            $nodes = $map->nodes()->get();
            for ($i = 0; $i < min($linkCount, $nodeCount - 1); $i++) {
                $map->links()->create([
                    'src_node_id' => $nodes[$i]->id,
                    'dst_node_id' => $nodes[$i + 1]->id,
                    'bandwidth_bps' => 1000000000,
                    'style' => ['color' => '#28a745']
                ]);
            }
        }

        return $map;
    }

    /**
     * Create a test user for authentication tests
     */
    protected function createTestUser()
    {
        // This would need to be adapted based on LibreNMS user model
        // For now, return a mock user
        return (object) [
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com'
        ];
    }

    /**
     * Assert that a map has the expected structure
     */
    protected function assertMapStructure($map, array $expectedKeys = [])
    {
        $defaultKeys = ['id', 'name', 'title', 'width', 'height', 'nodes', 'links'];
        $keysToCheck = array_merge($defaultKeys, $expectedKeys);

        foreach ($keysToCheck as $key) {
            $this->assertArrayHasKey($key, $map, "Map structure missing key: {$key}");
        }
    }

    /**
     * Assert that a node has the expected structure
     */
    protected function assertNodeStructure($node, array $expectedKeys = [])
    {
        $defaultKeys = ['id', 'label', 'x', 'y', 'device_id'];
        $keysToCheck = array_merge($defaultKeys, $expectedKeys);

        foreach ($keysToCheck as $key) {
            $this->assertArrayHasKey($key, $node, "Node structure missing key: {$key}");
        }
    }

    /**
     * Assert that a link has the expected structure
     */
    protected function assertLinkStructure($link, array $expectedKeys = [])
    {
        $defaultKeys = ['id', 'src', 'dst', 'bandwidth_bps'];
        $keysToCheck = array_merge($defaultKeys, $expectedKeys);

        foreach ($keysToCheck as $key) {
            $this->assertArrayHasKey($key, $link, "Link structure missing key: {$key}");
        }
    }
}