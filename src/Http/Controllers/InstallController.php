<?php
namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LibreNMS\Plugins\WeathermapNG\WeathermapNG;

class InstallController extends Controller
{
    public function index()
    {
        $requirements = $this->checkRequirements();
        $steps = [
            'requirements' => $this->checkRequirementsMet($requirements),
            'database' => $this->checkDatabaseReady(),
            'permissions' => $this->checkPermissions(),
            'plugin' => $this->checkPluginEnabled(),
            'complete' => false
        ];

        return view('WeathermapNG::install.index', compact('requirements', 'steps'));
    }

    public function install(Request $request)
    {
        try {
            $plugin = new WeathermapNG();

            // Run installation
            $result = $plugin->activate();

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'WeathermapNG installed successfully!',
                    'redirect' => url('plugin/WeathermapNG')
                ]);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Installation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function checkRequirements()
    {
        return [
            'php' => [
                'name' => 'PHP Version',
                'required' => '8.0+',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '8.0.0', '>=')
            ],
            'gd' => [
                'name' => 'GD Extension',
                'required' => 'Enabled',
                'current' => extension_loaded('gd') ? 'Enabled' : 'Disabled',
                'status' => extension_loaded('gd')
            ],
            'database' => [
                'name' => 'Database Connection',
                'required' => 'Connected',
                'current' => $this->testDatabaseConnection() ? 'Connected' : 'Failed',
                'status' => $this->testDatabaseConnection()
            ],
            'writable' => [
                'name' => 'Output Directory',
                'required' => 'Writable',
                'current' => is_writable(__DIR__ . '/../../../output') ? 'Writable' : 'Not Writable',
                'status' => is_writable(__DIR__ . '/../../../output')
            ]
        ];
    }

    private function checkRequirementsMet($requirements)
    {
        foreach ($requirements as $req) {
            if (!$req['status']) return false;
        }
        return true;
    }

    private function checkDatabaseReady()
    {
        try {
            $tables = DB::select("SHOW TABLES LIKE 'wmng_%'");
            return count($tables) === 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkPermissions()
    {
        $paths = [
            __DIR__ . '/../../../output',
            __DIR__ . '/../../../bin/map-poller.php'
        ];

        foreach ($paths as $path) {
            if (file_exists($path) && !is_writable($path)) {
                return false;
            }
        }
        return true;
    }

    private function checkPluginEnabled()
    {
        // Check if plugin is registered and enabled in LibreNMS
        // This would need to be implemented based on LibreNMS's plugin system
        return true; // Placeholder
    }

    private function testDatabaseConnection()
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
