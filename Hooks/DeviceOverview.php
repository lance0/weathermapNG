<?php

namespace LibreNMS\Plugins\WeathermapNG\Hooks;

use App\Plugins\Hooks\DeviceOverviewHook;
use App\Models\User;
use App\Models\Device;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;

class DeviceOverview extends DeviceOverviewHook
{
    /**
     * The view to render in device overview
     */
    public string $view = 'WeathermapNG::hooks.device-overview';
    
    /**
     * Provide data to the device overview view
     */
    public function data(Device $device, array $settings = []): array
    {
        // Find maps that contain this device
        $mapsWithDevice = [];
        
        try {
            // Get all nodes for this device
            $nodes = Node::where('device_id', $device->device_id)->get();
            
            // Get unique maps containing these nodes
            $mapIds = $nodes->pluck('map_id')->unique();
            $mapsWithDevice = Map::whereIn('id', $mapIds)->get();
            
            // For each map, get the specific node data
            foreach ($mapsWithDevice as $map) {
                $map->device_nodes = $nodes->where('map_id', $map->id);
            }
        } catch (\Exception $e) {
            // Database might not be set up yet
        }
        
        return [
            'title' => 'Network Maps',
            'device' => $device,
            'maps' => $mapsWithDevice,
            'has_maps' => count($mapsWithDevice) > 0,
        ];
    }
    
    /**
     * Only show if device has maps
     */
    public function authorize(User $user, Device $device): bool
    {
        // Check if device appears in any maps
        try {
            $nodeCount = Node::where('device_id', $device->device_id)->count();
            return $nodeCount > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}