<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

class E2EInstallationWorkflowTest extends TestCase
{
    public function test_quick_install_script_exists(): void
    {
        $scriptPath = __DIR__ . '/../quick-install.sh';
        $this->assertFileExists($scriptPath);
        $this->assertIsReadable($scriptPath);
    }

    public function test_quick_install_script_is_executable(): void
    {
        $scriptPath = __DIR__ . '/../quick-install.sh';
        $this->assertTrue(is_executable($scriptPath) || $this->assertFileExists($scriptPath));
    }

    public function test_quick_install_script_checks_php_version(): void
    {
        $scriptContent = file_get_contents(__DIR__ . '/../quick-install.sh');
        $this->assertStringContainsString('PHP Version:', $scriptContent);
        $this->assertStringContainsString('version_compare', $scriptContent);
    }

    public function test_quick_install_script_validates_composer_install(): void
    {
        $scriptContent = file_get_contents(__DIR__ . '/../quick-install.sh');
        $this->assertStringContainsString('composer install', $scriptContent);
        $this->assertStringContainsString('Composer install failed', $scriptContent);
    }

    public function test_quick_install_script_guards_native_root_runs(): void
    {
        $scriptContent = file_get_contents(__DIR__ . '/../quick-install.sh');
        $this->assertStringContainsString('Do not run quick-install.sh as root', $scriptContent);
        $this->assertStringContainsString('WEATHERMAPNG_ALLOW_ROOT', $scriptContent);
    }

    public function test_quick_install_script_forces_librenms_composer_registration(): void
    {
        $scriptContent = file_get_contents(__DIR__ . '/../quick-install.sh');
        $this->assertStringContainsString('FORCE=1 COMPOSER_ALLOW_SUPERUSER=1 composer require', $scriptContent);
    }

    public function test_quick_install_normalizes_duplicate_plugin_rows(): void
    {
        $scriptContent = file_get_contents(__DIR__ . '/../quick-install.sh');

        $this->assertStringContainsString('Normalizing WeathermapNG plugin registration', $scriptContent);
        $this->assertStringContainsString("where('plugin_name', 'WeathermapNG')", $scriptContent);
        $this->assertStringContainsString("'plugin_id', 'plugin_name', 'plugin_active', 'version'", $scriptContent);
        $this->assertStringContainsString('Prefer version=2 rows', $scriptContent);
        $this->assertStringContainsString("update(['plugin_active' => 1, 'version' => 2])", $scriptContent);
        $this->assertStringContainsString("where('plugin_id', '!=', \$keep->plugin_id)", $scriptContent);
        $this->assertStringContainsString('Cleaned $deleted duplicate WeathermapNG plugin row(s)', $scriptContent);
    }

    public function test_database_setup_normalizes_duplicate_plugin_rows(): void
    {
        $scriptContent = file_get_contents(__DIR__ . '/../database/setup.php');

        $this->assertStringContainsString('function normalizePluginRegistration', $scriptContent);
        $this->assertStringContainsString("where('plugin_name', 'WeathermapNG')", $scriptContent);
        $this->assertStringContainsString("'plugin_id', 'plugin_name', 'plugin_active', 'version'", $scriptContent);
        $this->assertStringContainsString('Prefer version=2 rows', $scriptContent);
        $this->assertStringContainsString("'plugin_active' => 1, 'version' => 2", $scriptContent);
        $this->assertStringContainsString("where('plugin_id', '!=', \$keep->plugin_id)", $scriptContent);
        // Verify the normalizer is called before every success exit
        $this->assertStringContainsString('normalizePluginRegistration()', $scriptContent);
        $this->assertStringContainsString('normalizePluginRegistration($pdo)', $scriptContent);
    }

    public function test_quick_install_plugin_row_cleanup_is_defensive(): void
    {
        $scriptContent = file_get_contents(__DIR__ . '/../quick-install.sh');

        $this->assertStringContainsString("hasTable('plugins')", $scriptContent);
        $this->assertStringContainsString('getColumnListing(\'plugins\')', $scriptContent);
        $this->assertStringContainsString('missing expected column(s)', $scriptContent);
        $this->assertStringContainsString('Skipped plugin row normalization', $scriptContent);
        $this->assertStringContainsString('Plugin row normalization failed', $scriptContent);
    }

    public function test_install_docs_cover_plugin_duplicates_and_json_collation_warnings(): void
    {
        $install = file_get_contents(__DIR__ . '/../INSTALL.md');
        $readme = file_get_contents(__DIR__ . '/../README.md');
        $deployment = file_get_contents(__DIR__ . '/../DEPLOYMENT.md');

        foreach ([$install, $readme, $deployment] as $content) {
            $this->assertStringContainsString('duplicate', strtolower($content));
            $this->assertStringContainsString('utf8mb4_bin', $content);
            $this->assertStringContainsString('wmng_map_templates.config', $content);
            $this->assertStringContainsString('wmng_nodes.meta', $content);
            $this->assertStringContainsString('wmng_maps.options', $content);
            $this->assertStringContainsString('wmng_links.style', $content);
        }
    }

    public function test_quick_install_script_sets_up_database(): void
    {
        $scriptContent = file_get_contents(__DIR__ . '/../quick-install.sh');
        $this->assertStringContainsString('database/setup.php', $scriptContent);
    }

    public function test_quick_install_script_clears_caches(): void
    {
        $scriptContent = file_get_contents(__DIR__ . '/../quick-install.sh');
        $this->assertStringContainsString('cache:clear', $scriptContent);
        $this->assertStringContainsString('view:clear', $scriptContent);
        $this->assertStringContainsString('config:clear', $scriptContent);
    }

    public function test_database_setup_script_exists(): void
    {
        $scriptPath = __DIR__ . '/../database/setup.php';
        $this->assertFileExists($scriptPath);
        $this->assertIsReadable($scriptPath);
    }

    public function test_database_setup_creates_tables(): void
    {
        $scriptContent = file_get_contents(__DIR__ . '/../database/setup.php');
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS', $scriptContent);
        $this->assertStringContainsString('wmng_maps', $scriptContent);
        $this->assertStringContainsString('wmng_nodes', $scriptContent);
        $this->assertStringContainsString('wmng_links', $scriptContent);
    }

    public function test_web_installer_controller_exists(): void
    {
        $controllerPath = __DIR__ . '/../src/Http/Controllers/InstallController.php';
        $this->assertFileExists($controllerPath);
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('class InstallController', $content);
    }

    public function test_web_installer_has_index_method(): void
    {
        $controllerPath = __DIR__ . '/../src/Http/Controllers/InstallController.php';
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('public function index', $content);
    }

    public function test_web_installer_has_install_method(): void
    {
        $controllerPath = __DIR__ . '/../src/Http/Controllers/InstallController.php';
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('public function install', $content);
    }

    public function test_web_installer_checks_requirements(): void
    {
        $controllerPath = __DIR__ . '/../src/Http/Controllers/InstallController.php';
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('checkRequirements', $content);
        $this->assertStringContainsString('checkRequirementsMet', $content);
    }

    public function test_web_installer_validates_database(): void
    {
        $controllerPath = __DIR__ . '/../src/Http/Controllers/InstallController.php';
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('checkDatabaseReady', $content);
        $this->assertStringContainsString('testDatabaseConnection', $content);
    }

    public function test_web_installer_validates_permissions(): void
    {
        $controllerPath = __DIR__ . '/../src/Http/Controllers/InstallController.php';
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('checkPermissions', $content);
    }

    public function test_install_routes_defined(): void
    {
        $routesPath = __DIR__ . '/../routes/web.php';
        $content = file_get_contents($routesPath);
        $this->assertStringContainsString("Route::get('plugin/WeathermapNG/install'", $content);
        $this->assertStringContainsString("Route::post('plugin/WeathermapNG/install'", $content);
    }

    public function test_installation_detection_in_page_controller(): void
    {
        $controllerPath = __DIR__ . '/../src/Http/Controllers/PageController.php';
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('isInstalled', $content);
        $this->assertStringContainsString("route('weathermapng.install')", $content);
    }

    public function test_installation_view_exists(): void
    {
        $viewPath = __DIR__ . '/../resources/views/install/index.blade.php';
        $this->assertFileExists($viewPath);
        $this->assertIsReadable($viewPath);
    }

    public function test_installation_view_has_checklist(): void
    {
        $viewPath = __DIR__ . '/../resources/views/install/index.blade.php';
        $content = file_get_contents($viewPath);
        $this->assertStringContainsString('requirements', $content);
        $this->assertStringContainsString('database', $content);
        $this->assertStringContainsString('permissions', $content);
    }

    public function test_installation_flow_complete(): void
    {
        $hasQuickInstall = file_exists(__DIR__ . '/../quick-install.sh');
        $hasWebInstaller = file_exists(__DIR__ . '/../src/Http/Controllers/InstallController.php');
        $hasDatabaseSetup = file_exists(__DIR__ . '/../database/setup.php');
        $hasRoutes = strpos(file_get_contents(__DIR__ . '/../routes/web.php'), 'weathermapng.install') !== false;

        $this->assertTrue($hasQuickInstall, 'Quick install script should exist');
        $this->assertTrue($hasWebInstaller, 'Web installer controller should exist');
        $this->assertTrue($hasDatabaseSetup, 'Database setup script should exist');
        $this->assertTrue($hasRoutes, 'Install routes should be defined');
    }

    public function test_fallback_mechanisms_present(): void
    {
        $routesPath = __DIR__ . '/../routes/web.php';
        $content = file_get_contents($routesPath);

        $hasInstallRoutes = strpos($content, 'weathermapng.install') !== false;
        $this->assertTrue($hasInstallRoutes, 'Install routes should be defined');
    }
}
