<?php

namespace LibreNMS\Plugins\WeathermapNG\Hooks;

use App\Plugins\Hooks\PortTabHook;
use App\Models\User;
use App\Models\Port;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;

class PortTab extends PortTabHook
{
    /**
     * The view to render in port tab
     */
    public string $view = 'WeathermapNG::hooks.port-tab';
    
    /**
     * Tab title
     */
    public string $name = 'Weathermap';
    
    /**
     * Provide data to the port tab view
     */
    public function data(Port $port, array $settings = []): array
    {
        $links = [];
        $utilization = null;
        
        try {
            // Find links using this port
            $links = Link::where('port_id_a', $port->port_id)
                ->orWhere('port_id_b', $port->port_id)
                ->with('map')
                ->get();
            
            // Get current utilization
            $portUtilService = new PortUtilService();
            $utilization = $portUtilService->getPortUtilization($port->port_id);
        } catch (\Exception $e) {
            // Handle errors gracefully
        }
        
        return [
            'title' => 'Weathermap Links',
            'port' => $port,
            'links' => $links,
            'utilization' => $utilization,
            'has_links' => count($links) > 0,
        ];
    }
    
    /**
     * Only show if port has weathermap links
     */
    public function authorize(User $user, Port $port): bool
    {
        try {
            $linkCount = Link::where('port_id_a', $port->port_id)
                ->orWhere('port_id_b', $port->port_id)
                ->count();
            return $linkCount > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}