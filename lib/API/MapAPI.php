<?php

// lib/API/MapAPI.php
namespace LibreNMS\Plugins\WeathermapNG\API;

use Illuminate\Http\Request;
use LibreNMS\Plugins\WeathermapNG\Map;
use LibreNMS\Plugins\WeathermapNG\DataSource;

class MapAPI
{
    public function render(Request $request, $mapId)
    {
        try {
            $map = new Map(config('weathermapng.map_dir', __DIR__ . '/../../config/maps/') . $mapId . '.conf');

            if (!$map->getId()) {
                return response()->json(['error' => 'Map not found'], 404);
            }

            // Load real-time data
            $map->loadData();

            $data = $map->toArray();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to render map',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function embed(Request $request, $mapId)
    {
        try {
            $map = new Map(config('weathermapng.map_dir', __DIR__ . '/../../config/maps/') . $mapId . '.conf');

            if (!$map->getId()) {
                abort(404, 'Map not found');
            }

            // Load real-time data
            $map->loadData();

            $mapData = $map->toArray();

            return view('plugins.WeathermapNG.embed', compact('mapData', 'mapId'));
        } catch (\Exception $e) {
            abort(500, 'Failed to load embedded map');
        }
    }

    public function listMaps(Request $request)
    {
        $mapsDir = config('weathermapng.map_dir', __DIR__ . '/../../config/maps/');

        if (!is_dir($mapsDir)) {
            return response()->json(['maps' => []]);
        }

        $maps = [];
        foreach (glob($mapsDir . '*.conf') as $file) {
            $mapId = basename($file, '.conf');
            $map = new Map($file);

            $maps[] = [
                'id' => $mapId,
                'title' => $map->getTitle(),
                'width' => $map->getWidth(),
                'height' => $map->getHeight(),
                'node_count' => count($map->getNodes()),
                'link_count' => count($map->getLinks()),
                'config_path' => $file,
                'output_path' => config('weathermapng.output_dir', __DIR__ . '/../../output/maps/') . $mapId . '.png'
            ];
        }

        return response()->json(['maps' => $maps]);
    }

    public function createMap(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'title' => 'string|max:255',
            'width' => 'integer|min:100|max:4096',
            'height' => 'integer|min:100|max:4096',
            'config' => 'string'
        ]);

        $mapId = preg_replace('/[^a-zA-Z0-9_-]/', '', $validated['name']);
        $configFile = config('weathermapng.map_dir', __DIR__ . '/../../config/maps/') . $mapId . '.conf';

        if (file_exists($configFile)) {
            return response()->json(['error' => 'Map already exists'], 409);
        }

        try {
            if (isset($validated['config'])) {
                // Use provided config
                file_put_contents($configFile, $validated['config']);
            } else {
                // Create basic config
                $config = "[global]\n";
                $config .= "width " . ($validated['width'] ?? 800) . "\n";
                $config .= "height " . ($validated['height'] ?? 600) . "\n";
                $config .= "title \"" . ($validated['title'] ?? $validated['name']) . "\"\n\n";
                $config .= "[node:node1]\n";
                $config .= "label \"Node 1\"\n";
                $config .= "x 100\n";
                $config .= "y 100\n\n";
                $config .= "[node:node2]\n";
                $config .= "label \"Node 2\"\n";
                $config .= "x 300\n";
                $config .= "y 100\n\n";
                $config .= "[link:link1]\n";
                $config .= "nodes node1 node2\n";
                $config .= "bandwidth 1000000000\n";

                file_put_contents($configFile, $config);
            }

            return response()->json([
                'status' => 'success',
                'id' => $mapId,
                'message' => 'Map created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create map',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateMap(Request $request, $mapId)
    {
        $validated = $request->validate([
            'config' => 'required|string',
            'title' => 'string|max:255',
            'width' => 'integer|min:100|max:4096',
            'height' => 'integer|min:100|max:4096'
        ]);

        $configFile = config('weathermapng.map_dir', __DIR__ . '/../../config/maps/') . $mapId . '.conf';

        if (!file_exists($configFile)) {
            return response()->json(['error' => 'Map not found'], 404);
        }

        try {
            file_put_contents($configFile, $validated['config']);

            return response()->json([
                'status' => 'success',
                'message' => 'Map updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update map',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteMap(Request $request, $mapId)
    {
        $configFile = config('weathermapng.map_dir', __DIR__ . '/../../config/maps/') . $mapId . '.conf';
        $outputFile = config('weathermapng.output_dir', __DIR__ . '/../../output/maps/') . $mapId . '.png';

        $deleted = false;

        if (file_exists($configFile)) {
            unlink($configFile);
            $deleted = true;
        }

        if (file_exists($outputFile)) {
            unlink($outputFile);
            $deleted = true;
        }

        if ($deleted) {
            return response()->json([
                'status' => 'success',
                'message' => 'Map deleted successfully'
            ]);
        } else {
            return response()->json(['error' => 'Map not found'], 404);
        }
    }

    public function getDevices(Request $request)
    {
        $devices = DataSource::getDevices();

        $formatted = $devices->map(function ($device) {
            return [
                'id' => $device->device_id ?? $device['device_id'],
                'hostname' => $device->hostname ?? $device['hostname'],
                'sysName' => $device->sysName ?? $device['sysName'],
                'status' => DataSource::getDeviceStatus($device->device_id ?? $device['device_id'])
            ];
        });

        return response()->json(['devices' => $formatted]);
    }

    public function getInterfaces(Request $request, $deviceId)
    {
        $interfaces = DataSource::getInterfaces($deviceId);

        $formatted = $interfaces->map(function ($interface) {
            return [
                'id' => $interface->port_id ?? $interface['port_id'],
                'name' => $interface->ifName ?? $interface['ifName'],
                'index' => $interface->ifIndex ?? $interface['ifIndex'],
                'status' => $interface->ifOperStatus ?? $interface['ifOperStatus']
            ];
        });

        return response()->json(['interfaces' => $formatted]);
    }
}
