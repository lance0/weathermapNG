<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Flow dots live on #overlay-canvas; links live on #map-canvas. If those
 * two elements don't share an origin — or if the overlay 2d transform
 * leaks across RAF frames — particles sit off the stroke.
 */
class OverlayAlignmentTest extends TestCase
{
    private string $js;
    private string $css;

    protected function setUp(): void
    {
        parent::setUp();
        $base = __DIR__ . '/../resources';
        $this->js = file_get_contents($base . '/js/embed-app.js');
        $this->css = file_get_contents($base . '/css/embed.css');
    }

    public function test_both_canvases_are_pinned_to_the_same_origin(): void
    {
        $this->assertStringContainsString('#map-canvas,', $this->css);
        $this->assertStringContainsString('#overlay-canvas {', $this->css);
        $this->assertMatchesRegularExpression(
            '/#map-canvas,\s*#overlay-canvas \{[^}]*position:\s*absolute;[^}]*top:\s*0;[^}]*left:\s*0;/s',
            $this->css,
            'map and overlay canvases must share top/left 0 so dots cannot drift'
        );
    }

    public function test_overlay_sync_does_not_use_bounding_client_rect(): void
    {
        $sync = $this->functionBody('syncOverlayCanvas');
        $this->assertStringNotContainsString(
            'getBoundingClientRect',
            $sync,
            'Measuring canvas offset while #loading is in-flow pins the overlay sideways'
        );
        $this->assertStringContainsString("overlayCanvas.style.left = '0'", $sync);
        $this->assertStringContainsString("overlayCanvas.style.top = '0'", $sync);
    }

    public function test_loading_is_hidden_before_first_overlay_sync(): void
    {
        $init = $this->functionBody('initCanvas');
        $hideAt = strpos($init, "getElementById('loading').style.display = 'none'");
        $syncAt = strpos($init, 'syncOverlayCanvas()');
        $this->assertNotFalse($hideAt, 'initCanvas must hide #loading');
        $this->assertNotFalse($syncAt, 'initCanvas must sync the overlay');
        $this->assertLessThan($syncAt, $hideAt, 'hide #loading before syncOverlayCanvas()');
    }

    public function test_flow_particles_restore_canvas_state(): void
    {
        $fn = $this->functionBody('drawFlowParticles');
        $this->assertSame(
            substr_count($fn, '.save()'),
            substr_count($fn, '.restore()'),
            'unmatched overlayCtx.save() compounds the transform and walks dots off the line'
        );
    }

    private function functionBody(string $name): string
    {
        $this->assertMatchesRegularExpression('/function ' . $name . '\s*\(/', $this->js);
        $start = strpos($this->js, 'function ' . $name);
        $this->assertNotFalse($start);
        $next = strpos($this->js, "\n    function ", $start + 1);
        return $next === false ? substr($this->js, $start) : substr($this->js, $start, $next - $start);
    }
}
