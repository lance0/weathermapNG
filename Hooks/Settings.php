<?php

namespace LibreNMS\Plugins\WeathermapNG\Hooks;

use App\Plugins\Hooks\SettingsHook;
use App\Models\User;

class Settings extends SettingsHook
{
    /**
     * The view to render in settings page
     */
    public string $view = 'WeathermapNG::hooks.settings';
    
    /**
     * Settings section name
     */
    public string $name = 'WeathermapNG';
    
    /**
     * Provide data to the settings view
     */
    public function data(array $settings = []): array
    {
        // Load current settings
        $currentSettings = [
            'poll_interval' => config('weathermapng.poll_interval', 300),
            'default_width' => config('weathermapng.default_width', 800),
            'default_height' => config('weathermapng.default_height', 600),
            'rrd_base' => config('weathermapng.rrd_base', '/opt/librenms/rrd'),
            'enable_api_fallback' => config('weathermapng.enable_api_fallback', true),
            'allow_embed' => config('weathermapng.allow_embed', true),
            'cache_ttl' => config('weathermapng.cache_ttl', 300),
            'debug' => config('weathermapng.debug', false),
        ];
        
        return [
            'title' => 'WeathermapNG Settings',
            'settings' => $currentSettings,
            'saved' => request()->get('saved', false),
        ];
    }
    
    /**
     * Handle settings save
     */
    public function save(array $data): bool
    {
        // Save settings to config file or database
        $configPath = base_path('config/weathermapng.php');
        
        try {
            $config = require $configPath;
            
            // Update config values
            foreach ($data as $key => $value) {
                if (array_key_exists($key, $config)) {
                    $config[$key] = $value;
                }
            }
            
            // Write config back
            $configContent = "<?php\n\nreturn " . var_export($config, true) . ";\n";
            file_put_contents($configPath, $configContent);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Only admins can change settings
     */
    public function authorize(User $user): bool
    {
        return $user->can('admin');
    }
}