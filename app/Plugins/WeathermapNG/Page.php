<?php

namespace App\Plugins\WeathermapNG;

use App\Plugins\Hooks\PageHook;
use Illuminate\Support\Facades\DB;

class Page extends PageHook
{
    public string $view = 'weathermapng::page';
    
    public function data(): array
    {
        return [
            'title' => 'WeathermapNG - Network Weather Maps',
            'maps' => $this->getMaps(),
        ];
    }

    private function getMaps()
    {
        // Check if our tables exist
        if (! $this->tablesExist()) {
            return collect();
        }

        try {
            return DB::table('wmng_maps')
                ->select('id', 'name', 'description', 'width', 'height', 'updated_at')
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    private function tablesExist()
    {
        try {
            return DB::getSchemaBuilder()->hasTable('wmng_maps');
        } catch (\Exception $e) {
            return false;
        }
    }
}
