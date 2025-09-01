<?php
/**
 * WeathermapNG Plugin Bootstrap
 *
 * Compatibility file for verification scripts and traditional plugin expectations.
 *
 * IMPORTANT: This plugin uses LibreNMS 24.x hook-based architecture.
 * The actual plugin functionality is implemented via hooks in:
 * - app/Plugins/WeathermapNG/Menu.php
 * - app/Plugins/WeathermapNG/Page.php
 * - app/Plugins/WeathermapNG/Settings.php
 *
 * This file exists solely for compatibility with tools that expect
 * a traditional plugin structure. All real functionality is in the hooks.
 */

// No namespace to avoid conflicts
// This file is for compatibility only

class WeathermapNG
{
    public $name = 'WeathermapNG';
    public $description = 'Modern interactive network weathermap for LibreNMS';
    public $architecture = 'hook-based';
    public $librenms_version = '24.x+';

    public function __construct()
    {
        // Plugin initialization is handled by LibreNMS hooks
        // See: app/Plugins/WeathermapNG/ directory
    }

    public function activate()
    {
        // Activation logic is handled by hooks
        // This method exists for compatibility only
        return true;
    }

    public function deactivate()
    {
        // Deactivation logic is handled by hooks
        // This method exists for compatibility only
        return true;
    }

    public function uninstall()
    {
        // Cleanup logic is handled by hooks
        // This method exists for compatibility only
        return true;
    }

    public function getVersion()
    {
        return '1.0.0';
    }

    public function getInfo()
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->getVersion(),
            'author' => 'LibreNMS Community',
            'email' => 'info@librenms.org',
            'architecture' => $this->architecture,
            'librenms_version' => $this->librenms_version,
            'hooks' => [
                'menu' => 'app/Plugins/WeathermapNG/Menu.php',
                'page' => 'app/Plugins/WeathermapNG/Page.php',
                'settings' => 'app/Plugins/WeathermapNG/Settings.php'
            ],
            'note' => 'This plugin uses LibreNMS 24.x hook-based architecture. ' .
                     'Functionality is implemented via hooks, not this bootstrap file.'
        ];
    }

    /**
     * Get hook information for debugging
     */
    public function getHooksInfo()
    {
        return [
            'menu_hook' => 'Adds WeathermapNG to LibreNMS navigation menu',
            'page_hook' => 'Provides main weathermap interface and editor',
            'settings_hook' => 'Adds weathermap configuration to LibreNMS settings',
            'location' => 'app/Plugins/WeathermapNG/',
            'architecture' => 'Hook-based (LibreNMS 24.x+)'
        ];
    }
}