<?php

namespace LibreNMS\Plugins\WeathermapNG\Console\Commands;

use Illuminate\Console\Command;
use LibreNMS\Plugins\WeathermapNG\Services\MapService;

class CreateMapCommand extends Command
{
    protected $signature = 'weathermapng:create-map
        {name : Map name (alpha-numeric, hyphens, underscores)}
        {--title= : Map title}
        {--width=800 : Map width in pixels}
        {--height=600 : Map height in pixels}
        {--background=#ffffff : Background hex color}
        {--tags=* : Tags for the map}';

    protected $description = 'Create a new WeathermapNG map';

    public function __construct(
        private readonly MapService $mapService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $title = $this->option('title');
        $width = (int) $this->option('width');
        $height = (int) $this->option('height');
        $background = $this->option('background');
        $tags = $this->option('tags');

        $validator = \Illuminate\Support\Facades\Validator::make([
            'name' => $name,
            'title' => $title,
            'width' => $width,
            'height' => $height,
        ], [
            'name' => 'required|string|max:255|alpha_dash|regex:/^[a-z0-9_-]+$/i',
            'title' => 'nullable|string|max:255',
            'width' => 'nullable|integer|min:100|max:4096',
            'height' => 'nullable|integer|min:100|max:4096',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $options = ['background' => $background];
        if (! empty($tags)) {
            $options['tags'] = $tags;
        }

        $map = $this->mapService->createMap([
            'name' => $name,
            'title' => $title ?? $name,
            'width' => $width,
            'height' => $height,
            'options' => $options,
        ]);

        $this->info("Map created: ID {$map->id}, Name: {$map->name}");

        return self::SUCCESS;
    }
}
