<?php

namespace LibreNMS\Plugins\WeathermapNG\Console\Commands;

use Illuminate\Console\Command;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Services\AutoDiscoveryService;

class DiscoverCommand extends Command
{
    protected $signature = 'weathermapng:discover
        {map_id : The ID of the map to seed with discovered nodes/links}
        {--os=* : Comma-separated or repeated OS filters (e.g. --os=iosxe --os=ios)}';

    protected $description = 'Auto-discover devices and seed a map with topology from LibreNMS LLDP/CDP data';

    public function __construct(
        private readonly AutoDiscoveryService $discoveryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $mapId = (int) $this->argument('map_id');

        try {
            $map = $this->resolveMap($mapId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error("Map with ID {$mapId} not found.");

            return self::FAILURE;
        }

        $osFilters = $this->option('os');

        $params = $this->discoveryService->validateDiscoveryParams([
            'min_degree' => 0,
            'os' => implode(',', $osFilters),
        ]);

        $result = $this->discoveryService->discoverAndSeedMap($map, $params);

        $nodesAdded = (int) ($result['nodes_added'] ?? 0);
        $linksAdded = (int) ($result['links_added'] ?? 0);
        $this->info("Discovery complete: {$nodesAdded} nodes, {$linksAdded} links added to map {$map->name}.");

        return self::SUCCESS;
    }

    /**
     * Resolve the target map. Extracted so the command can be tested without
     * a live database connection.
     */
    protected function resolveMap(int $mapId): Map
    {
        return Map::findOrFail($mapId);
    }
}
