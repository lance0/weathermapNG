<?php

namespace LibreNMS\Plugins\WeathermapNG\Console\Commands;

use Illuminate\Console\Command;
use LibreNMS\Plugins\WeathermapNG\Services\LegacyConfService;
use LibreNMS\Plugins\WeathermapNG\Models\Map;

class ExportMapCommand extends Command
{
    protected $signature = 'weathermapng:export
        {map_id : The ID of the map to export}
        {--output= : Write output to this file path instead of stdout}
        {--format=json : Output format (json or conf)}';

    protected $description = 'Export a WeathermapNG map as JSON or legacy .conf';

    public function handle(): int
    {
        $mapId = (int) $this->argument('map_id');
        $format = $this->option('format') ?: 'json';

        if (!in_array($format, ['json', 'conf'], true)) {
            $this->error("Unsupported format: {$format} (use json or conf)");

            return self::FAILURE;
        }

        try {
            $map = Map::with(['nodes', 'links'])->findOrFail($mapId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error("Map with ID {$mapId} not found.");

            return self::FAILURE;
        }

        if ($format === 'conf') {
            $output = (new LegacyConfService())->toConf($map);
        } else {
            $output = json_encode(
                $map->toJsonModel(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
        }

        $file = $this->option('output');

        if ($file) {
            $written = file_put_contents($file, $output);
            if ($written === false) {
                $this->error("Failed to write to {$file}");

                return self::FAILURE;
            }
            $this->info("Exported map {$map->name} (ID {$map->id}) to {$file}");

            return self::SUCCESS;
        }

        $this->line($output);

        return self::SUCCESS;
    }

}
