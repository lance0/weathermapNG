<?php

namespace App\Plugins\WeathermapNG;

class Menu
{
    public function data(): array
    {
        // Handle case where Laravel helpers might not be available (e.g., in testing)
        $url = '/plugins/weathermapng';
        if (function_exists('url')) {
            try {
                $url = url('/plugins/weathermapng');
            } catch (Exception $e) {
                // Keep default URL if url() helper fails
            }
        }

        return [
            'title' => 'WeathermapNG',
            'url' => $url,
            'icon' => 'fa-network-wired',
        ];
    }

    public function authorize($user): bool
    {
        // Allow all authenticated users to see the menu
        return $user !== null;
    }
}