<?php

namespace App\Plugins\WeathermapNG;

class Menu
{
    public function data(): array
    {
        return [
            'title' => 'WeathermapNG',
            'url' => url('/plugins/weathermapng'),
            'icon' => 'fa-network-wired',
        ];
    }

    public function authorize($user): bool
    {
        // Allow all authenticated users to see the menu
        return $user !== null;
    }
}