<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\Cache;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;

class MapCacheService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const MAP_LIST_KEY = 'weathermapng:maps:all';
    private const MAP_DETAIL_KEY = 'weathermapng:map:';
    private const MAP_NODES_KEY = 'weathermapng:map:nodes:';
    private const MAP_LINKS_KEY = 'weathermapng:map:links:';
    private const DEVICE_MAP_KEY = 'weathermapng:devices:map';

    public function clearMapCache(int $mapId): void
    {
        Cache::forget(self::MAP_LIST_KEY);
        Cache::forget(self::MAP_DETAIL_KEY . $mapId);
        Cache::forget(self::MAP_NODES_KEY . $mapId);
        Cache::forget(self::MAP_LINKS_KEY . $mapId);
    }

    public function clearDeviceCache(): void
    {
        Cache::forget(self::DEVICE_MAP_KEY);
    }

    public function getAllMaps(): array
    {
        return Cache::remember(self::MAP_LIST_KEY, self::CACHE_TTL, function () {
            return Map::withCount(['nodes', 'links'])
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    public function getMapDetail(int $mapId): ?array
    {
        return Cache::remember(self::MAP_DETAIL_KEY . $mapId, self::CACHE_TTL, function () use ($mapId) {
            return Map::withCount(['nodes', 'links'])
                ->find($mapId)
                ?->toArray();
        });
    }

    public function getMapNodes(int $mapId): array
    {
        return Cache::remember(self::MAP_NODES_KEY . $mapId, self::CACHE_TTL / 2, function () use ($mapId) {
            return Node::where('map_id', $mapId)
                ->with('device')
                ->get()
                ->toArray();
        });
    }

    public function getMapLinks(int $mapId): array
    {
        return Cache::remember(self::MAP_LINKS_KEY . $mapId, self::CACHE_TTL / 2, function () use ($mapId) {
            return Link::where('map_id', $mapId)
                ->with(['sourceNode.device', 'destNode.device', 'portA', 'portB'])
                ->get()
                ->toArray();
        });
    }

    public function getDeviceMap(): array
    {
        return Cache::remember(self::DEVICE_MAP_KEY, self::CACHE_TTL, function () {
            if (!class_exists('App\\Models\\Device')) {
                return [];
            }

            return \App\Models\Device::select(['device_id', 'hostname', 'sysName', 'location'])
                ->get()
                ->keyBy('device_id')
                ->toArray();
        });
    }

    public function getMapForEditor(int $mapId): array
    {
        $mapKey = self::MAP_DETAIL_KEY . $mapId . ':editor';
        
        return Cache::remember($mapKey, self::CACHE_TTL / 4, function () use ($mapId) {
            $map = Map::withCount(['nodes', 'links'])->find($mapId);
            
            if (!$map) {
                return null;
            }

            $mapData = $map->toArray();
            $mapData['nodes'] = Node::where('map_id', $mapId)
                ->with('device')
                ->get()
                ->toArray();
            $mapData['links'] = Link::where('map_id', $mapId)
                ->with(['sourceNode.device', 'destNode.device', 'portA', 'portB'])
                ->get()
                ->toArray();

            return $mapData;
        });
    }

    public function invalidateMap(int $mapId): void
    {
        $this->clearMapCache($mapId);
        $this->clearDeviceCache();
    }
}
