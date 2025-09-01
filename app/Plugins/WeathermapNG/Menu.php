<?php

namespace App\Plugins\WeathermapNG;

class Menu
{
    public string $view = 'weathermapng::menu';
    public function data(): array
    {
        return [
            'title' => 'Network Maps',
            'url' => '/plugin/WeathermapNG',
            'icon' => 'fa-network-wired',
        ];
    }

    public function authorize($user): bool
    {
        // Allow all authenticated users to see the menu
        return $user !== null;
    }
}