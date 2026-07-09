<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

class DiagnosticsTest extends TestCase
{
    private string $controller;
    private string $view;
    private string $routes;
    private string $menuHook;
    private string $menuBlade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = file_get_contents(__DIR__ . '/../src/Http/Controllers/HealthController.php');
        $this->view = file_get_contents(__DIR__ . '/../resources/views/diagnostics.blade.php');
        $this->routes = file_get_contents(__DIR__ . '/../routes/web.php');
        $this->menuHook = file_get_contents(__DIR__ . '/../src/Hooks/MenuEntry.php');
        $this->menuBlade = file_get_contents(__DIR__ . '/../resources/views/menu.blade.php');
    }

    public function test_diagnostics_route_is_registered(): void
    {
        $this->assertStringContainsString("Route::get('plugin/WeathermapNG/diagnostics'", $this->routes);
        $this->assertStringContainsString("'weathermapng.diagnostics'", $this->routes);
    }

    public function test_diagnostics_method_exists_and_requires_admin(): void
    {
        $this->assertStringContainsString('public function diagnostics(): \\Illuminate\\View\\View', $this->controller);

        $start = strpos($this->controller, 'public function diagnostics()');
        $end = strpos($this->controller, 'public function metrics()', $start);
        $body = substr($this->controller, $start, $end - $start);
        $this->assertStringContainsString('$this->requireAdmin();', $body);
    }

    public function test_diagnostics_uses_route_has_for_status(): void
    {
        $this->assertStringContainsString('Route::has($r[\'route\'])', $this->controller);
        $this->assertStringContainsString("if (!isset(\$r['url'])) {", $this->controller);
        $this->assertStringContainsString("\$r['url'] = '#'", $this->controller);
    }

    public function test_diagnostics_view_renders(): void
    {
        foreach (['Overall', 'Counts', 'Health Checks', 'Routes', 'Writable Paths'] as $section) {
            $this->assertStringContainsString($section, $this->view);
        }
        $this->assertStringContainsString('$overallStatus', $this->view);
        $this->assertStringContainsString('$checks', $this->view);
        $this->assertStringContainsString('$stats', $this->view);
        $this->assertStringContainsString('$routes', $this->view);
        $this->assertStringContainsString('$paths', $this->view);
    }

    public function test_diagnostics_view_does_not_link_missing_routes(): void
    {
        $this->assertStringContainsString("@if(\$route['url'] !== '#')", $this->view);
        $this->assertStringContainsString("<span class=\"text-muted\">{{ \$route['name'] }}</span>", $this->view);
    }

    public function test_menu_hook_passes_admin_flag_and_diagnostics_url(): void
    {
        $this->assertStringContainsString("'is_admin' => \$isAdmin", $this->menuHook);
        $this->assertStringContainsString("'diagnostics_url'", $this->menuHook);
    }

    public function test_menu_blade_shows_diagnostics_link_for_admins(): void
    {
        $this->assertStringContainsString("@if(\$is_admin ?? false)", $this->menuBlade);
        $this->assertStringContainsString("{{ \$diagnostics_url }}", $this->menuBlade);
        $this->assertStringContainsString('fa-stethoscope', $this->menuBlade);
    }
}
