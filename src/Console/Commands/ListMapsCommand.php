<?php

namespace LibreNMS\Plugins\WeathermapNG\Console\Commands;

use Illuminate\Console\Command;
use LibreNMS\Plugins\WeathermapNG\Models\Map;

class ListMapsCommand extends Command
{
    protected $signature = 'weathermapng:list-maps
        {--format=table : Output format (table or json)}';

    protected $description = 'List all WeathermapNG maps';

    public function handle(): int
    {
        $maps = Map::with(['nodes', 'links'])->get();

        $format = $this->option('format');

        if ($format === 'json') {
            $rows = $maps->map(fn ($map) => [
                'id' => $map->id,
                'name' => $map->name,
                'title' => $map->title,
                'width' => $map->width,
                'height' => $map->height,
                'background' => $map->background,
                'tags' => $map->tags,
                'nodes' => $map->nodes->count(),
                'links' => $map->links->count(),
                'updated_at' => $map->updated_at?->toIso8601String(),
            ])->toArray();

            $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $rows = $maps->map(fn ($map) => [
            $map->id,
            $map->name,
            $map->title,
            $map->nodes->count(),
            $map->links->count(),
            $map->updated_at?->diffForHumans() ?? '—',
        ])->toArray();

        $this->table(
            ['ID', 'Name', 'Title', 'Nodes', 'Links', 'Updated'],
            $rows
        );

        return self::SUCCESS;
    }
}
