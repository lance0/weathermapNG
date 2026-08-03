<?php

namespace LibreNMS\Plugins\WeathermapNG\Console\Commands;

use Illuminate\Console\Command;
use LibreNMS\Plugins\WeathermapNG\Models\Map;

class ExportMapCommand extends Command
{
    protected $signature = 'weathermapng:export
        {map_id : The ID of the map to export}
        {--output= : Write output to this file path instead of stdout}
        {--format=json : Output format (json)}';

    protected $description = 'Export a WeathermapNG map as JSON';

    public function handle(): int
    {
        $mapId = (int) $this->argument('map_id');

        try {
            $map = Map::with(['nodes', 'links'])->findOrFail($mapId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error("Map with ID {$mapId} not found.");

            return self::FAILURE;
        }

        $json = json_encode(
            $map->toJsonModel(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        $output = $this->option('output');

        if ($output) {
            $written = file_put_contents($output, $json);
            if ($written === false) {
                $this->error("Failed to write to {$output}");

                return self::FAILURE;
            }
            $this->info("Exported map {$map->name} (ID {$map->id}) to {$output}");

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
