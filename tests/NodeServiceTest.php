<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\NodeService;
use LibreNMS\Plugins\WeathermapNG\Models\Map;

class NodeServiceTest extends TestCase
{
    /**
     * storeNodes() guards against wiping a populated map via an empty
     * payload. The guard fires before DB::transaction, so no DB is
     * needed to pin it. Node persistence itself is covered by the
     * container integration tests.
     */
    public function test_store_empty_array_on_populated_map_throws(): void
    {
        $service = new NodeService();
        $map = $this->makeMap(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot replace nodes with an empty array');

        $service->storeNodes($map, []);
    }

    /**
     * All three node write paths (create, update, store) must catch
     * InvalidArgumentException and map to 422, so an empty-after-strip
     * label (e.g. "<b></b>") returns a validation error, not a 500 or a
     * silently-persisted empty label. The normalization itself is unit-tested
     * in NodeLabelNormalizerTest; this guards the controller wiring.
     */
    public function test_all_write_paths_map_invalid_argument_to_422(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/MapNodeController.php');
        $this->assertEquals(
            3,
            substr_count($content, "catch (\\InvalidArgumentException \$e)"),
            'create, update, and store must each catch InvalidArgumentException'
        );
        $this->assertEquals(
            3,
            substr_count($content, ", 422)"),
            'all three node write paths must map validation errors to 422'
        );
    }

    private function makeMap(bool $mapHasNodes): Map
    {
        $map = $this->createStub(Map::class);
        $relation = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['exists', 'delete'])
            ->getMock();
        $relation->method('exists')->willReturn($mapHasNodes);
        $relation->method('delete')->willReturn(0);
        $map->method('nodes')->willReturn($relation);
        return $map;
    }
}
