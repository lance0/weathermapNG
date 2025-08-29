<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for WeathermapNG tests
 *
 * This provides common functionality for testing the plugin
 * without requiring Laravel's testing framework.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up test environment
        $this->setupTestEnvironment();

        // Set up in-memory database
        $this->setupDatabase();

        // Clean up any leftover test data
        $this->cleanupTestData();
    }

    /**
     * Set up the database schema for testing
     */
    protected function setupDatabase(): void
    {
        // Create tables in SQLite in-memory database
        $schema = "
            CREATE TABLE IF NOT EXISTS wmng_maps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL UNIQUE,
                title VARCHAR(255),
                options TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS wmng_nodes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                map_id INTEGER NOT NULL,
                label VARCHAR(255) NOT NULL,
                x DECIMAL(8,2) NOT NULL DEFAULT 0,
                y DECIMAL(8,2) NOT NULL DEFAULT 0,
                device_id INTEGER,
                meta TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (map_id) REFERENCES wmng_maps(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS wmng_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                map_id INTEGER NOT NULL,
                src_node_id INTEGER NOT NULL,
                dst_node_id INTEGER NOT NULL,
                port_id_a INTEGER,
                port_id_b INTEGER,
                bandwidth_bps BIGINT,
                style TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (map_id) REFERENCES wmng_maps(id) ON DELETE CASCADE,
                FOREIGN KEY (src_node_id) REFERENCES wmng_nodes(id),
                FOREIGN KEY (dst_node_id) REFERENCES wmng_nodes(id)
            );
        ";

        try {
            \DB::unprepared($schema);
        } catch (\Exception $e) {
            // Schema might already exist, ignore
        }
    }

    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void
    {
        try {
            \DB::table('wmng_links')->truncate();
            \DB::table('wmng_nodes')->truncate();
            \DB::table('wmng_maps')->truncate();
        } catch (\Exception $e) {
            // Tables might not exist yet, ignore
        }
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    /**
     * Create a mock node for testing
     */
    protected function createMockNode(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Node
    {
        // Create a simple mock object that behaves like a Node
        $node = new class($attributes) {
            public $id;
            public $map_id;
            public $label;
            public $x;
            public $y;
            public $device_id;
            public $meta;

            public function __construct($attributes = [])
            {
                $this->id = $attributes['id'] ?? 1;
                $this->map_id = $attributes['map_id'] ?? 1;
                $this->label = $attributes['label'] ?? 'Test Node';
                $this->x = $attributes['x'] ?? 100;
                $this->y = $attributes['y'] ?? 100;
                $this->device_id = $attributes['device_id'] ?? 1;
                $this->meta = $attributes['meta'] ?? [];
            }

            public function getLabel()
            {
                return $this->label;
            }

            public function getPosition()
            {
                return ['x' => $this->x, 'y' => $this->y];
            }

            public function getDeviceId()
            {
                return $this->device_id;
            }

            public function getDeviceNameAttribute()
            {
                return $this->device_id ? "Device {$this->device_id}" : null;
            }

            public function getStatusAttribute()
            {
                return $this->device_id ? 'up' : 'unknown';
            }
        };

        return $node;
    }

    /**
     * Create a mock link for testing
     */
    protected function createMockLink(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Link
    {
        $sourceNode = $this->createMockNode(['id' => $attributes['src_node_id'] ?? 1]);
        $targetNode = $this->createMockNode(['id' => $attributes['dst_node_id'] ?? 2]);

        // Create a simple mock object that behaves like a Link
        $link = new class($attributes, $sourceNode, $targetNode) {
            public $id;
            public $map_id;
            public $src_node_id;
            public $dst_node_id;
            public $bandwidth_bps;
            public $style;
            private $sourceNode;
            private $targetNode;

            public function __construct($attributes, $sourceNode, $targetNode)
            {
                $this->id = $attributes['id'] ?? 1;
                $this->map_id = $attributes['map_id'] ?? 1;
                $this->src_node_id = $attributes['src_node_id'] ?? 1;
                $this->dst_node_id = $attributes['dst_node_id'] ?? 2;
                $this->bandwidth_bps = $attributes['bandwidth_bps'] ?? 1000000000;
                $this->style = $attributes['style'] ?? ['color' => '#28a745'];
                $this->sourceNode = $sourceNode;
                $this->targetNode = $targetNode;
            }

            public function getId()
            {
                return $this->id;
            }

            public function getSourceId()
            {
                return $this->src_node_id;
            }

            public function getTargetId()
            {
                return $this->dst_node_id;
            }

            public function getBandwidth()
            {
                return $this->bandwidth_bps;
            }

            public function getStyleAttribute()
            {
                return $this->style;
            }

            public function getSourceNode()
            {
                return $this->sourceNode;
            }

            public function getTargetNode()
            {
                return $this->targetNode;
            }

            public function getUtilization()
            {
                return 0.5; // Mock utilization
            }

            public function setUtilization($util)
            {
                // Mock setter
            }

            public function getStatus()
            {
                return 'normal';
            }

            public function getColor()
            {
                return $this->style['color'] ?? '#28a745';
            }

            public function getWidth()
            {
                return 3;
            }
        };

        return $link;
    }

    /**
     * Create database tables
     */
    protected function createTables(): void
    {
        // Create wmng_maps table
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS wmng_maps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL UNIQUE,
                title VARCHAR(255),
                options TEXT,
                created_at DATETIME,
                updated_at DATETIME
            )
        ");

        // Create wmng_nodes table
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS wmng_nodes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                map_id INTEGER NOT NULL,
                label VARCHAR(255) NOT NULL,
                x DECIMAL(8,2) NOT NULL,
                y DECIMAL(8,2) NOT NULL,
                device_id INTEGER,
                meta TEXT,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (map_id) REFERENCES wmng_maps(id) ON DELETE CASCADE
            )
        ");

        // Create wmng_links table
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS wmng_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                map_id INTEGER NOT NULL,
                src_node_id INTEGER NOT NULL,
                dst_node_id INTEGER NOT NULL,
                port_id_a INTEGER,
                port_id_b INTEGER,
                bandwidth_bps BIGINT,
                style TEXT,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (map_id) REFERENCES wmng_maps(id) ON DELETE CASCADE,
                FOREIGN KEY (src_node_id) REFERENCES wmng_nodes(id) ON DELETE CASCADE,
                FOREIGN KEY (dst_node_id) REFERENCES wmng_nodes(id) ON DELETE CASCADE
            )
        ");
    }

    /**
     * Create a test node in the database
     */
    protected function createTestNode(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Node
    {
        return \LibreNMS\Plugins\WeathermapNG\Models\Node::create(array_merge([
            'map_id' => $attributes['map_id'] ?? 1,
            'label' => $attributes['label'] ?? 'Test Node',
            'x' => $attributes['x'] ?? 100,
            'y' => $attributes['y'] ?? 100,
            'device_id' => $attributes['device_id'] ?? 1,
            'meta' => $attributes['meta'] ?? []
        ], $attributes));
    }

    /**
     * Create a test link in the database
     */
    protected function createTestLink(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Link
    {
        return \LibreNMS\Plugins\WeathermapNG\Models\Link::create(array_merge([
            'map_id' => $attributes['map_id'] ?? 1,
            'src_node_id' => $attributes['src_node_id'] ?? 1,
            'dst_node_id' => $attributes['dst_node_id'] ?? 2,
            'port_id_a' => $attributes['port_id_a'] ?? null,
            'port_id_b' => $attributes['port_id_b'] ?? null,
            'bandwidth_bps' => $attributes['bandwidth_bps'] ?? 1000000000,
            'style' => $attributes['style'] ?? []
        ], $attributes));
    }

    /**
     * Create database tables for testing
     */
    private function createTables(): void
    {
        // Create wmng_maps table
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS wmng_maps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL UNIQUE,
                title VARCHAR(255),
                options TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create wmng_nodes table
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS wmng_nodes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                map_id INTEGER NOT NULL,
                label VARCHAR(255) NOT NULL,
                x DECIMAL(8,2) NOT NULL DEFAULT 0,
                y DECIMAL(8,2) NOT NULL DEFAULT 0,
                device_id INTEGER,
                meta TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (map_id) REFERENCES wmng_maps(id) ON DELETE CASCADE
            )
        ");

        // Create wmng_links table
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS wmng_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                map_id INTEGER NOT NULL,
                src_node_id INTEGER NOT NULL,
                dst_node_id INTEGER NOT NULL,
                port_id_a INTEGER,
                port_id_b INTEGER,
                bandwidth_bps BIGINT,
                style TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (map_id) REFERENCES wmng_maps(id) ON DELETE CASCADE,
                FOREIGN KEY (src_node_id) REFERENCES wmng_nodes(id) ON DELETE CASCADE,
                FOREIGN KEY (dst_node_id) REFERENCES wmng_nodes(id) ON DELETE CASCADE
            )
        ");
    }

    /**
     * Set up in-memory database for testing
     */
    protected function setupDatabase(): void
    {
        // Create in-memory SQLite database
        $pdo = new \PDO('sqlite::memory:');

        // Create tables manually (since we can't run migrations in test environment)
        $this->createTables($pdo);

        // Store PDO instance for use in tests
        $this->pdo = $pdo;
    }

    /**
     * Create database tables for testing
     */
    private function createTables($pdo): void
    {
        // Create wmng_maps table
        $pdo->exec("
            CREATE TABLE wmng_maps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL UNIQUE,
                title VARCHAR(255),
                options TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create wmng_nodes table
        $pdo->exec("
            CREATE TABLE wmng_nodes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                map_id INTEGER NOT NULL,
                label VARCHAR(255) NOT NULL,
                x DECIMAL(8,2) NOT NULL,
                y DECIMAL(8,2) NOT NULL,
                device_id INTEGER,
                meta TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (map_id) REFERENCES wmng_maps(id) ON DELETE CASCADE
            )
        ");

        // Create wmng_links table
        $pdo->exec("
            CREATE TABLE wmng_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                map_id INTEGER NOT NULL,
                src_node_id INTEGER NOT NULL,
                dst_node_id INTEGER NOT NULL,
                port_id_a INTEGER,
                port_id_b INTEGER,
                bandwidth_bps BIGINT,
                style TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (map_id) REFERENCES wmng_maps(id) ON DELETE CASCADE,
                FOREIGN KEY (src_node_id) REFERENCES wmng_nodes(id),
                FOREIGN KEY (dst_node_id) REFERENCES wmng_nodes(id)
            )
        ");
    }

    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec("DELETE FROM wmng_links");
            $this->pdo->exec("DELETE FROM wmng_nodes");
            $this->pdo->exec("DELETE FROM wmng_maps");
        }
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    /**
     * Create a mock node for testing
     */
    protected function createMockNode(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Node
    {
        // Create a simple mock object that behaves like a Node
        return new class($attributes) {
            public $id;
            public $map_id;
            public $label;
            public $x;
            public $y;
            public $device_id;
            public $meta;

            public function __construct($attributes = [])
            {
                $this->id = $attributes['id'] ?? 1;
                $this->map_id = $attributes['map_id'] ?? 1;
                $this->label = $attributes['label'] ?? 'Test Node';
                $this->x = $attributes['x'] ?? 100;
                $this->y = $attributes['y'] ?? 100;
                $this->device_id = $attributes['device_id'] ?? 1;
                $this->meta = $attributes['meta'] ?? [];
            }

            public function getLabel()
            {
                return $this->label;
            }

            public function getPosition()
            {
                return ['x' => $this->x, 'y' => $this->y];
            }

            public function getDeviceId()
            {
                return $this->device_id;
            }

            public function getDeviceNameAttribute()
            {
                return $this->device_id ? "Device {$this->device_id}" : null;
            }

            public function getStatusAttribute()
            {
                return $this->device_id ? 'up' : 'unknown';
            }
        };
    }

    /**
     * Create a mock link for testing
     */
    protected function createMockLink(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Link
    {
        $sourceNode = $this->createMockNode(['id' => 1]);
        $targetNode = $this->createMockNode(['id' => 2]);

        // Create a simple mock object that behaves like a Link
        return new class($attributes, $sourceNode, $targetNode) {
            public $id;
            public $map_id;
            public $src_node_id;
            public $dst_node_id;
            public $bandwidth_bps;
            public $style;
            private $sourceNode;
            private $targetNode;

            public function __construct($attributes, $sourceNode, $targetNode)
            {
                $this->id = $attributes['id'] ?? 1;
                $this->map_id = $attributes['map_id'] ?? 1;
                $this->src_node_id = $attributes['src_node_id'] ?? 1;
                $this->dst_node_id = $attributes['dst_node_id'] ?? 2;
                $this->bandwidth_bps = $attributes['bandwidth_bps'] ?? 1000000000;
                $this->style = $attributes['style'] ?? ['color' => '#28a745'];
                $this->sourceNode = $sourceNode;
                $this->targetNode = $targetNode;
            }

            public function getId()
            {
                return $this->id;
            }

            public function getSourceId()
            {
                return $this->src_node_id;
            }

            public function getTargetId()
            {
                return $this->dst_node_id;
            }

            public function getBandwidth()
            {
                return $this->bandwidth_bps;
            }

            public function getStyleAttribute()
            {
                return $this->style;
            }

            public function getUtilization()
            {
                return 0.5; // Mock utilization
            }

            public function getStatus()
            {
                return 'normal';
            }

            public function setUtilization($utilization)
            {
                // Mock implementation
            }
        };
    }

        // Set up basic LibreNMS-style functions if they don't exist
        // Note: These are mock implementations for testing
        // In a real LibreNMS environment, these would be provided by the framework

        // Note: Config function will be provided by the testing framework
        // or LibreNMS bootstrap in production
    }



    /**
     * Create a mock map for testing
     */
    protected function createMockMap(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Map
    {
        $map = new \LibreNMS\Plugins\WeathermapNG\Models\Map();

        // Set basic properties
        $map->id = $attributes['id'] ?? 1;
        $map->name = $attributes['name'] ?? 'test_map_' . uniqid();
        $map->title = $attributes['title'] ?? 'Test Map';
        $map->options = $attributes['options'] ?? ['width' => 800, 'height' => 600];

        return $map;
    }

    /**
     * Create a mock node for testing
     */
    protected function createMockNode(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Node
    {
        // Create a simple mock object that behaves like a Node
        return new class($attributes) {
            public $id;
            public $map_id;
            public $label;
            public $x;
            public $y;
            public $device_id;
            public $meta;

            public function __construct($attributes = [])
            {
                $this->id = $attributes['id'] ?? 1;
                $this->map_id = $attributes['map_id'] ?? 1;
                $this->label = $attributes['label'] ?? 'Test Node';
                $this->x = $attributes['x'] ?? 100;
                $this->y = $attributes['y'] ?? 100;
                $this->device_id = $attributes['device_id'] ?? 1;
                $this->meta = $attributes['meta'] ?? [];
            }

            public function getLabel() { return $this->label; }
            public function getPosition() { return ['x' => $this->x, 'y' => $this->y]; }
            public function getDeviceId() { return $this->device_id; }
            public function getDeviceNameAttribute() { return 'Test Device'; }
            public function getStatusAttribute() { return 'up'; }
        };
    }

    /**
     * Create a mock link for testing
     */
    protected function createMockLink(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Link
    {
        // Create a simple mock object that behaves like a Link
        return new class($attributes) {
            public $id;
            public $map_id;
            public $src_node_id;
            public $dst_node_id;
            public $bandwidth_bps;
            public $style;

            public function __construct($attributes = [])
            {
                $this->id = $attributes['id'] ?? 1;
                $this->map_id = $attributes['map_id'] ?? 1;
                $this->src_node_id = $attributes['src_node_id'] ?? 1;
                $this->dst_node_id = $attributes['dst_node_id'] ?? 2;
                $this->bandwidth_bps = $attributes['bandwidth_bps'] ?? 1000000000;
                $this->style = $attributes['style'] ?? ['color' => '#28a745'];
            }

            public function getId() { return $this->id; }
            public function getSourceId() { return $this->src_node_id; }
            public function getTargetId() { return $this->dst_node_id; }
            public function getBandwidth() { return $this->bandwidth_bps; }
            public function getStyleAttribute() { return $this->style; }
            public function getUtilization() { return 0.5; }
            public function setUtilization($util) { /* mock */ }
            public function getStatus() { return 'normal'; }
        };
    }

    /**
     * Create a mock node for testing
     */
    protected function createMockNode(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Node
    {
        // Create a simple mock object that behaves like a Node
        return new class($attributes) {
            public $id;
            public $map_id;
            public $label;
            public $x;
            public $y;
            public $device_id;
            public $meta;

            public function __construct($attributes = [])
            {
                $this->id = $attributes['id'] ?? 1;
                $this->map_id = $attributes['map_id'] ?? 1;
                $this->label = $attributes['label'] ?? 'Test Node';
                $this->x = $attributes['x'] ?? 100;
                $this->y = $attributes['y'] ?? 100;
                $this->device_id = $attributes['device_id'] ?? 1;
                $this->meta = $attributes['meta'] ?? [];
            }

            public function getLabel() { return $this->label; }
            public function getPosition() { return ['x' => $this->x, 'y' => $this->y]; }
            public function getDeviceId() { return $this->device_id; }
            public function getDeviceNameAttribute() { return 'Test Device'; }
            public function getStatusAttribute() { return 'up'; }
        };
    }

    /**
     * Create a mock link for testing
     */
    protected function createMockLink(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Link
    {
        $sourceNode = $this->createMockNode(['id' => 1]);
        $targetNode = $this->createMockNode(['id' => 2]);

        // Create a simple mock object that behaves like a Link
        return new class($attributes, $sourceNode, $targetNode) {
            public $id;
            public $map_id;
            public $src_node_id;
            public $dst_node_id;
            public $bandwidth_bps;
            public $style;
            private $sourceNode;
            private $targetNode;

            public function __construct($attributes, $sourceNode, $targetNode)
            {
                $this->id = $attributes['id'] ?? 1;
                $this->map_id = $attributes['map_id'] ?? 1;
                $this->src_node_id = $attributes['src_node_id'] ?? 1;
                $this->dst_node_id = $attributes['dst_node_id'] ?? 2;
                $this->bandwidth_bps = $attributes['bandwidth_bps'] ?? 1000000000;
                $this->style = $attributes['style'] ?? ['color' => '#28a745'];
                $this->sourceNode = $sourceNode;
                $this->targetNode = $targetNode;
            }

            public function getId() { return $this->id; }
            public function getSourceId() { return $this->src_node_id; }
            public function getTargetId() { return $this->dst_node_id; }
            public function getBandwidth() { return $this->bandwidth_bps; }
            public function getStyleAttribute() { return $this->style; }
            public function getSourceNode() { return $this->sourceNode; }
            public function getTargetNode() { return $this->targetNode; }
            public function getUtilization() { return 0.5; }
            public function setUtilization($util) { /* mock */ }
            public function getStatus() { return 'normal'; }
            public function getColor() { return '#28a745'; }
            public function getWidth() { return 3; }
        };
    }

    /**
     * Create a mock map for testing
     */
    protected function createMockMap(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Map
    {
        // Create a simple mock object that behaves like a Map
        return new class($attributes) {
            public $id;
            public $name;
            public $title;
            public $options;

            public function __construct($attributes = [])
            {
                $this->id = $attributes['id'] ?? 1;
                $this->name = $attributes['name'] ?? 'test_map_' . uniqid();
                $this->title = $attributes['title'] ?? 'Test Map';
                $this->options = $attributes['options'] ?? ['width' => 800, 'height' => 600];
            }

            public function getWidthAttribute() {
                return $this->options['width'] ?? 800;
            }

            public function getHeightAttribute() {
                return $this->options['height'] ?? 600;
            }

            public function getBackgroundAttribute() {
                return $this->options['background'] ?? '#ffffff';
            }

            public function toJsonModel() {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'title' => $this->title,
                    'width' => $this->getWidthAttribute(),
                    'height' => $this->getHeightAttribute(),
                    'background' => $this->getBackgroundAttribute(),
                    'options' => $this->options,
                    'nodes' => [],
                    'links' => [],
                    'metadata' => [
                        'total_nodes' => 0,
                        'total_links' => 0,
                        'last_updated' => date('c')
                    ]
                ];
            }
        };
    }

    /**
     * Create a mock link for testing
     */
    protected function createMockLink(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Link
    {
        $sourceNode = $this->createMockNode(['id' => 1]);
        $targetNode = $this->createMockNode(['id' => 2]);

        // Create a mock link without database operations
        $link = $this->getMockBuilder(\LibreNMS\Plugins\WeathermapNG\Models\Link::class)
            ->setConstructorArgs([1, $sourceNode, $targetNode, $attributes])
            ->getMock();

        return $link;
    }

    /**
     * Assert that an object has the expected attributes
     */
    protected function assertHasAttributes($object, array $attributes): void
    {
        foreach ($attributes as $attribute => $expectedValue) {
            if (is_callable([$object, $attribute])) {
                $actualValue = $object->$attribute();
            } else {
                $actualValue = $object->$attribute ?? null;
            }

            $this->assertEquals($expectedValue, $actualValue,
                "Attribute '{$attribute}' does not match expected value");
        }
    }

    /**
     * Create a test node in the database
     */
    protected function createTestNode(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Node
    {
        return \LibreNMS\Plugins\WeathermapNG\Models\Node::create(array_merge([
            'map_id' => $attributes['map_id'] ?? 1,
            'label' => $attributes['label'] ?? 'Test Node',
            'x' => $attributes['x'] ?? 100,
            'y' => $attributes['y'] ?? 100,
            'device_id' => $attributes['device_id'] ?? 1,
            'meta' => $attributes['meta'] ?? []
        ], $attributes));
    }

    /**
     * Create a test link in the database
     */
    protected function createTestLink(array $attributes = []): \LibreNMS\Plugins\WeathermapNG\Models\Link
    {
        return \LibreNMS\Plugins\WeathermapNG\Models\Link::create(array_merge([
            'map_id' => $attributes['map_id'] ?? 1,
            'src_node_id' => $attributes['src_node_id'] ?? 1,
            'dst_node_id' => $attributes['dst_node_id'] ?? 2,
            'port_id_a' => $attributes['port_id_a'] ?? 1,
            'port_id_b' => $attributes['port_id_b'] ?? 2,
            'bandwidth_bps' => $attributes['bandwidth_bps'] ?? 1000000000,
            'style' => $attributes['style'] ?? ['color' => '#28a745']
        ], $attributes));
    }
    }
}