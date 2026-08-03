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

    protected $description = 'Auto-discover devices and seed a map (currently disabled)';

    public function __construct(
        private readonly AutoDiscoveryService $discoveryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $mapId = (int) $this->argument('map_id');

        try {
            $map = Map::findOrFail($mapId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error("Map with ID {$mapId} not found.");

            return self::FAILURE;
        }

        $osFilters = $this->option('os');

        $params = $this->discoveryService->validateDiscoveryParams([
            'min_degree' => 0,
            'os' => implode(',', $osFilters),
        ]);

        $this->warn('Note: Auto-discovery is currently disabled. No nodes or links will be created.');
        $this->warn('Future versions will use LibreNMS LLDP/CDP data for topology discovery.');

        $result = $this->discoveryService->discoverAndSeedMap($map, $params);

        if (empty($result)) {
            $this->info('Discovery completed (no results — feature disabled).');

            return self::SUCCESS;
        }

        $nodesCreated = count($result['nodes'] ?? []);
        $linksCreated = count($result['links'] ?? []);
        $this->info("Discovery complete: {$nodesCreated} nodes, {$linksCreated} links added to map {$map->name}.");

        return self::SUCCESS;
    }
}
