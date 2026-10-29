<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use LibreNMS\Plugins\WeathermapNG\Models\{Map, Node, Link, MapVersion};
use LibreNMS\Plugins\WeathermapNG\Services\{MapVersionService, WebhookDispatcher};
use PHPUnit\Framework\TestCase;

class MapVersionRestoreTest extends TestCase
{
    private MapVersionService $service;
    private array $notifications = [];

    protected function setUp(): void
    {
        $app = Facade::getFacadeApplication();
        $capsule = new Capsule($app);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $app->instance('db', $capsule->getDatabaseManager());
        $app->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());
        Facade::clearResolvedInstance('db');
        Facade::clearResolvedInstance('db.schema');
        $capsule->getConnection()->setTransactionManager(new DatabaseTransactionsManager());
        (require __DIR__ . '/../database/migrations/2025_08_29_000001_create_weathermapng_tables.php')->up();
        (require __DIR__ . '/../database/migrations/2026_09_14_000001_add_parent_map_id_to_wmng_maps.php')->up();
        Schema::create('wmng_map_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('map_id')->constrained('wmng_maps');
            $t->string('name')->nullable();
            $t->text('description')->nullable();
            $t->text('config_snapshot')->nullable();
            $t->string('created_by')->nullable();
            $t->timestamp('created_at');
        });
        $webhooks = $this->createMock(WebhookDispatcher::class);
        $webhooks->method('dispatch')->willReturnCallback(function ($event, $map) {
            $this->notifications[] = [$event, $map];
        });
        $this->service = new MapVersionService($webhooks);
    }

    private function graph(): Map
    {
        $map = Map::create(['name' => 'test-map', 'title' => 'Original', 'options' => ['width' => 1200, 'tags' => ['original']]]);
        $a = Node::create(['map_id' => $map->id, 'label' => 'A', 'x' => -50.5, 'y' => 7000]);
        $b = Node::create(['map_id' => $map->id, 'label' => 'B', 'x' => 10, 'y' => 20]);
        Link::create(['map_id' => $map->id, 'src_node_id' => $a->id, 'dst_node_id' => $b->id, 'style' => ['color' => '#abc']]);
        return $map;
    }

    private function rows(): array
    {
        return array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            ['wmng_maps', 'wmng_nodes', 'wmng_links', 'wmng_map_versions']);
    }

    public function testEmptyRootCannotEraseGraph(): void
    {
        $map = $this->graph();
        $version = MapVersion::create(['map_id' => $map->id, 'name' => 'Corrupt', 'config_snapshot' => []]);
        $before = $this->rows();
        $rejected = false;
        try {
            $this->service->restoreVersion($version);
        } catch (\InvalidArgumentException $e) {
            $rejected = true;
        }
        $this->assertSame($before, $this->rows(), 'An empty root snapshot must not erase nodes and links.');
        $this->assertTrue($rejected);
        $this->assertSame([], $this->notifications);
    }

    private function rejectSnapshot(Map $map, mixed $snapshot): void
    {
        $version = MapVersion::create(['map_id' => $map->id, 'name' => 'Invalid', 'config_snapshot' => $snapshot]);
        $before = $this->rows();
        $this->notifications = [];
        try {
            $this->service->restoreVersion($version);
            $this->fail('Expected invalid snapshot rejection.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame($before, $this->rows());
            $this->assertSame([], $this->notifications);
        }
    }

    public function testMalformedRecordsAndReferencesAreRejectedBeforeAnyMutation(): void
    {
        $map = $this->graph();
        $snapshot = $this->service->createVersion($map, 'Valid')->config_snapshot;
        $nodeId = array_key_first($snapshot['nodes']);
        $linkId = array_key_first($snapshot['links']);
        $bad = [null, 'bad json', ['map' => $snapshot['map'], 'nodes' => []]];
        foreach ([['map', 'id', 999], ['map', 'parent_map_id', $map->id], ['map', 'options', 'bad']] as [$section, $field, $value]) {
            $copy = $snapshot;
            $copy[$section][$field] = $value;
            $bad[] = $copy;
        }
        foreach (['id' => 0, 'label' => [], 'x' => 'INF', 'y' => null, 'meta' => ['sub_map_id' => 999]] as $field => $value) {
            $copy = $snapshot;
            $copy['nodes'][$nodeId][$field] = $value;
            $bad[] = $copy;
        }
        $copy = $snapshot;
        $copy['nodes'] = [$copy['nodes'][$nodeId], $copy['nodes'][$nodeId]];
        $bad[] = $copy;
        $copy = $snapshot;
        $copy['nodes'][] = 'broken';
        $bad[] = $copy;
        $copy = $snapshot;
        $copy['links'][$linkId]['src_node_id'] = 999;
        $bad[] = $copy;
        $copy = $snapshot;
        $copy['links'] = [$copy['links'][$linkId], $copy['links'][$linkId]];
        $bad[] = $copy;
        $copy = $snapshot;
        $copy['map']['parent_map_id'] = 999;
        $bad[] = $copy;
        foreach ($bad as $invalid) {
            $this->rejectSnapshot($map, $invalid);
        }
    }

    public function testCompleteSnapshotRestoresAllConfigurationAndKeepsRecoverableHistory(): void
    {
        $map = $this->graph();
        $parent = Map::create(['name' => 'parent']);
        $map->update(['title' => null, 'description' => 'Original description', 'parent_map_id' => $parent->id,
            'options' => ['width' => null, 'tags' => ['original'], 'default_node_style' => ['shape' => 'rect']]]);
        $map->forceFill(['width' => 321, 'height' => 432])->save();
        $node = $map->nodes()->first();
        $node->update(['meta' => ['sub_map_id' => $parent->id, 'icon' => 'router'], 'device_id' => 7]);
        $version = $this->service->createVersion($map, 'Original');
        $original = $version->config_snapshot;
        $originalRow = $version->fresh()->getRawOriginal();
        $map->update(['name' => 'renamed', 'title' => 'Changed', 'description' => null, 'parent_map_id' => null,
            'options' => ['width' => 900, 'tags' => ['changed'], 'extra' => true]]);
        $map->forceFill(['width' => 999, 'height' => 888])->save();
        $node->update(['label' => 'Changed node', 'meta' => null]);
        Node::create(['map_id' => $map->id, 'label' => 'New', 'x' => 0, 'y' => 0]);
        $before = $this->service->createVersion($map, 'Current')->config_snapshot;
        $count = MapVersion::count();
        // Both the version model and its loaded map relation can be stale.
        $version->load('map');
        $version->config_snapshot = [];
        $restored = $this->service->restoreVersion($version, 42);
        $this->assertNull($restored->title);
        $this->assertSame('test-map', $restored->name);
        $this->assertSame($count + 2, MapVersion::count());
        $history = MapVersion::versions($map->id)->get();
        $this->assertSame($original, $history[0]->config_snapshot);
        $this->assertSame($before, $history[1]->config_snapshot);
        $this->assertSame('42', $history[1]->created_by);
        $this->assertSame($originalRow, $version->fresh()->getRawOriginal());
        $this->assertSame($history[0]->id, $this->service->getLatestVersion($map)->id);
        // The checkpoint itself can undo the restore without losing options or IDs.
        $this->service->restoreVersion($history[1]);
        $this->assertSame($before, $this->service->getLatestVersion($map)->config_snapshot);
    }

    public function testCaptureReadsFreshPersistedMapAndRelations(): void
    {
        $map = $this->graph();
        $map->load(['nodes', 'links']);
        Map::whereKey($map->id)->update(['description' => 'Written elsewhere']);
        $map->nodes()->first()->update(['label' => 'Fresh label']);
        $map->links()->delete();
        $snapshot = $this->service->createVersion($map, 'Fresh')->config_snapshot;
        $this->assertSame('Written elsewhere', $snapshot['map']['description']);
        $this->assertSame('Fresh label', reset($snapshot['nodes'])['label']);
        $this->assertSame([], $snapshot['links']);
    }

    public function testLegacyListsRestoreRecordedFieldsWithoutDiscardingUnrecordedOptions(): void
    {
        $map = $this->graph();
        $map->update(['description' => 'Preserve', 'options' => ['width' => 1500, 'tags' => ['preserve']]]);
        $snapshot = $this->service->createVersion($map, 'Capture')->config_snapshot;
        $snapshot['map'] = ['id' => (string) $map->id, 'name' => $map->name, 'title' => null, 'width' => '1300', 'height' => 500, 'background' => null];
        $snapshot['nodes'] = array_values($snapshot['nodes']);
        $snapshot['links'] = array_values($snapshot['links']);
        $snapshot['nodes'][0]['map_id'] = 999;
        $snapshot['links'][0]['map_id'] = 999;
        $snapshot['nodes'][0]['x'] = '-9999.5';
        $version = MapVersion::create(['map_id' => $map->id, 'config_snapshot' => $snapshot]);
        $restored = $this->service->restoreVersion($version);
        $this->assertSame('Preserve', $restored->description);
        $this->assertSame(['width' => '1300', 'tags' => ['preserve'], 'height' => 500, 'background' => null], $restored->options);
        $this->assertNull($restored->title);
        $this->assertSame(-9999.5, $restored->nodes()->first()->x);
        $this->assertSame((int) $map->id, (int) $restored->nodes()->first()->map_id);
        $this->assertSame((int) $map->id, (int) $restored->links()->first()->map_id);
    }

    public function testWellShapedEmptyGraphIsRestorableAndRecoverable(): void
    {
        $map = $this->graph();
        $version = MapVersion::create(['map_id' => $map->id, 'config_snapshot' => ['map' => ['id' => $map->id], 'nodes' => [], 'links' => []]]);
        $this->service->restoreVersion($version);
        $this->assertSame(0, $map->nodes()->count());
        $this->assertSame(0, $map->links()->count());
        $checkpoint = MapVersion::versions($map->id)->get()[1];
        $this->assertCount(2, $checkpoint->config_snapshot['nodes']);
        $this->service->restoreVersion($checkpoint);
        $this->assertSame(2, $map->nodes()->count());
        $this->assertSame(1, $map->links()->count());
    }

    public function testOccupiedGlobalIdsCannotDamageEitherMap(): void
    {
        $map = $this->graph();
        $other = Map::create(['name' => 'other']);
        $node = Node::create(['map_id' => $other->id, 'label' => 'Other', 'x' => 0, 'y' => 0]);
        $link = Link::create(['map_id' => $other->id, 'src_node_id' => $node->id, 'dst_node_id' => $node->id]);
        $snapshot = $this->service->createVersion($map, 'Valid')->config_snapshot;
        $copy = $snapshot;
        $copy['nodes'][array_key_first($copy['nodes'])]['id'] = $node->id;
        $this->rejectSnapshot($map, $copy);
        $copy = $snapshot;
        $copy['links'][array_key_first($copy['links'])]['id'] = $link->id;
        $this->rejectSnapshot($map, $copy);
    }

    public function testFailuresAfterDeletionAndAfterHistoryInsertionRollEverythingBack(): void
    {
        $map = $this->graph();
        $version = $this->service->createVersion($map, 'Target');
        $map->update(['title' => 'Current']);
        foreach ([
            "CREATE TRIGGER reject_restore BEFORE INSERT ON wmng_nodes BEGIN SELECT RAISE(ABORT, 'injected node failure'); END",
            "CREATE TRIGGER reject_restore BEFORE INSERT ON wmng_map_versions WHEN NEW.name LIKE 'Restored version%' BEGIN SELECT RAISE(ABORT, 'injected history failure'); END",
        ] as $trigger) {
            DB::statement($trigger);
            $before = $this->rows();
            $this->notifications = [];
            try {
                $this->service->restoreVersion($version);
                $this->fail('Expected injected failure.');
            } catch (\Illuminate\Database\QueryException $e) {
                $this->assertStringContainsString('injected', $e->getMessage());
                $this->assertSame($before, $this->rows());
                $this->assertSame([], $this->notifications);
            } finally {
                DB::statement('DROP TRIGGER reject_restore');
            }
        }
    }

    public function testNestedRestoreNotifiesOnlyAfterOuterCommitAndNeverOnRollback(): void
    {
        $map = $this->graph();
        $version = $this->service->createVersion($map, 'Target');
        $map->update(['title' => 'Current']);
        $before = $this->rows();
        $this->notifications = [];
        DB::beginTransaction();
        $this->service->restoreVersion($version);
        $this->assertSame([], $this->notifications);
        DB::rollBack();
        $this->assertSame([], $this->notifications);
        $this->assertSame($before, $this->rows());
        DB::beginTransaction();
        $this->service->restoreVersion($version);
        $this->assertSame([], $this->notifications);
        DB::commit();
        $this->assertCount(2, $this->notifications);
    }

    public function testNotificationFailureDoesNotReportCommittedRestoreAsFailed(): void
    {
        $map = $this->graph();
        $version = $this->service->createVersion($map, 'Target');
        $map->update(['title' => 'Current']);
        $webhooks = $this->createMock(WebhookDispatcher::class);
        $webhooks->method('dispatch')->willThrowException(new \RuntimeException('delivery failure'));
        $restored = (new MapVersionService($webhooks))->restoreVersion($version);
        $this->assertSame('Original', $restored->title);
        $this->assertSame(3, MapVersion::count());
    }

    public function testDeliveryAndLoggingFailuresCannotChangeCommittedRestoreResult(): void
    {
        $map = $this->graph();
        $version = $this->service->createVersion($map, 'Target');
        $map->update(['title' => 'Current']);
        $webhooks = $this->createMock(WebhookDispatcher::class);
        $webhooks->method('dispatch')->willThrowException(new \RuntimeException('delivery failure'));
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning')->willThrowException(new \RuntimeException('logging failure'));
        $originalLogger = Log::getFacadeRoot();
        Log::swap($logger);
        try {
            $restored = (new MapVersionService($webhooks))->restoreVersion($version);
            $this->assertSame('Original', $restored->title);
            $this->assertSame('Original', $map->fresh()->title);
            $this->assertSame(3, MapVersion::count());
            $this->assertSame($version->config_snapshot, $this->service->getLatestVersion($map)->config_snapshot);
        } finally {
            Log::swap($originalLogger);
        }
    }

    public function testSameSecondHistoryOrdersByNewestId(): void
    {
        $map = $this->graph();
        $first = $this->service->createVersion($map, 'First');
        $second = $this->service->createVersion($map, 'Second');
        MapVersion::query()->update(['created_at' => '2026-01-01 00:00:00']);
        $this->assertSame($second->id, $this->service->getLatestVersion($map)->id);
        $this->assertSame([$second->id, $first->id], MapVersion::versions($map->id)->pluck('id')->all());
    }
}
