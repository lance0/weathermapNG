<?php

namespace App\Plugins\WeathermapNG;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Page
{
    public function data(Request $request): array
    {
        return [
            'title' => 'WeathermapNG - Network Weather Maps',
            'maps' => $this->getMaps(),
            'request' => $request,
        ];
    }

    public function authorize($user): bool
    {
        // Allow all authenticated users to access the page
        return $user !== null;
    }

    private function getMaps()
    {
        // Check if our tables exist
        if (!$this->tablesExist()) {
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