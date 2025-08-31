<?php

namespace App\Plugins\WeathermapNG;

class Settings
{
    public function settings(): array
    {
        return [
            'weathermapng_refresh_interval' => [
                'type' => 'number',
                'default' => 300,
                'label' => 'Map refresh interval (seconds)',
                'description' => 'How often to refresh the weather maps',
                'min' => 30,
                'max' => 3600,
            ],
            'weathermapng_enable_animations' => [
                'type' => 'boolean',
                'default' => true,
                'label' => 'Enable map animations',
                'description' => 'Show animated transitions on weather maps',
            ],
            'weathermapng_default_width' => [
                'type' => 'number',
                'default' => 800,
                'label' => 'Default map width (pixels)',
                'min' => 400,
                'max' => 2000,
            ],
            'weathermapng_default_height' => [
                'type' => 'number',
                'default' => 600,
                'label' => 'Default map height (pixels)',
                'min' => 300,
                'max' => 1500,
            ],
            'weathermapng_thresholds' => [
                'type' => 'text',
                'default' => '50,80,95',
                'label' => 'Traffic thresholds (%)',
                'description' => 'Comma-separated percentage values for traffic coloring',
            ],
        ];
    }

    public function authorize($user): bool
    {
        // Only allow admin users to modify settings
        return $user && $user->hasRole('admin');
    }
}