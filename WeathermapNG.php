<?php
// WeathermapNG.php
namespace LibreNMS\Plugins;

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

class WeathermapNG
{
    public static function menu()
    {
        echo '<li><a href="' . url('plugins/weathermapng') . '">WeathermapNG</a></li>';
        echo '<li><a href="' . url('plugins/weathermapng/editor') . '">Map Editor</a></li>';
    }

    public static function init()
    {
        $configPath = __DIR__ . '/config/settings.php';
        if (file_exists($configPath)) {
            $config = include $configPath;
            config(['weathermapng' => $config]);
        }
    }

    public static function routes()
    {
        Route::middleware(['auth'])->group(function () {
            Route::get('/plugins/weathermapng', [self::class, 'index'])->name('weathermapng.index');
            Route::get('/plugins/weathermapng/editor', [self::class, 'editor'])->name('weathermapng.editor');
            Route::get('/plugins/weathermapng/api/maps', [self::class, 'apiMaps'])->name('weathermapng.api.maps');
            Route::get('/plugins/weathermapng/api/map/{id}', [self::class, 'apiMap'])->name('weathermapng.api.map');
            Route::post('/plugins/weathermapng/api/map', [self::class, 'storeMap'])->name('weathermapng.api.store');
            Route::get('/plugins/weathermapng/embed/{id}', [self::class, 'embed'])->name('weathermapng.embed');
        });
    }

    public function index()
    {
        $maps = $this->getAvailableMaps();
        return view('plugins.WeathermapNG.index', compact('maps'));
    }

    public function editor()
    {
        $devices = \LibreNMS\Plugins\WeathermapNG\DataSource::getDevices();
        return view('plugins.WeathermapNG.editor', compact('devices'));
    }

    public function apiMaps()
    {
        return response()->json($this->getAvailableMaps());
    }

    public function apiMap($id)
    {
        $mapData = $this->loadMapData($id);
        return response()->json($mapData);
    }

    public function embed($id)
    {
        $mapData = $this->loadMapData($id);
        return view('plugins.WeathermapNG.embed', compact('mapData', 'id'));
    }

    public function storeMap(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'config' => 'required|string'
        ]);

        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $validated['name']);
        $configPath = config('weathermapng.map_dir') . $filename . '.conf';

        file_put_contents($configPath, $validated['config']);

        return response()->json([
            'status' => 'success',
            'id' => $filename
        ]);
    }

    private function getAvailableMaps()
    {
        $mapsDir = config('weathermapng.map_dir', __DIR__ . '/config/maps/');
        $maps = [];

        if (!is_dir($mapsDir)) {
            return $maps;
        }

        foreach (glob($mapsDir . '*.conf') as $file) {
            $maps[] = [
                'id' => basename($file, '.conf'),
                'name' => basename($file, '.conf'),
                'file' => basename($file, '.conf') . '.png',
                'config_path' => $file
            ];
        }
        return $maps;
    }

    private function loadMapData($id)
    {
        $configFile = config('weathermapng.map_dir', __DIR__ . '/config/maps/') . $id . '.conf';

        if (!file_exists($configFile)) {
            return ['error' => 'Map not found'];
        }

        $config = parse_ini_file($configFile, true);

        return [
            'metadata' => [
                'title' => $id,
                'width' => $config['global']['width'] ?? 800,
                'height' => $config['global']['height'] ?? 600,
                'last_updated' => date('c', filemtime($configFile))
            ],
            'config' => $config
        ];
    }
}