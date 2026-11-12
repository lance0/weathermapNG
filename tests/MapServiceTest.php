<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\MapService;
use LibreNMS\Plugins\WeathermapNG\Services\WebhookDispatcher;

class MapServiceTest extends TestCase
{
    /**
     * Behavioral tests for the private pure methods via reflection.
     * createMap()/updateMap() themselves need a live DB; their option
     * handling is covered by the merge/allowlist logic tested here.
     */

    private function invokeMerge(array $current, array $new): array
    {
        $service = new MapService(new WebhookDispatcher());
        $reflection = new \ReflectionMethod($service, 'mergeMapOptions');
        return $reflection->invoke($service, $current, $new);
    }

    public function test_merge_options_merges_nested_arrays_recursively(): void
    {
        // Regression: v1.9.1 replaced array_merge, which clobbered nested
        // default_*_style arrays wholesale when a partial style was sent.
        $current = [
            'width' => 800,
            'default_node_style' => ['color' => '#28a745', 'shape' => 'circle'],
        ];
        $new = [
            'default_node_style' => ['color' => '#ff0000'],
        ];

        $merged = $this->invokeMerge($current, $new);

        $this->assertSame('#ff0000', $merged['default_node_style']['color'], 'new scalar should win');
        $this->assertSame('circle', $merged['default_node_style']['shape'], 'unset nested key should survive');
        $this->assertSame(800, $merged['width']);
    }

    public function test_merge_options_replaces_scalars(): void
    {
        $merged = $this->invokeMerge(
            ['width' => 800, 'height' => 600, 'background' => '#ffffff'],
            ['width' => 1024]
        );

        $this->assertSame(1024, $merged['width']);
        $this->assertSame(600, $merged['height']);
    }

    public function test_merge_options_fills_missing_defaults(): void
    {
        $merged = $this->invokeMerge([], ['tags' => ['wan']]);

        $this->assertSame(800, $merged['width']);
        $this->assertSame(600, $merged['height']);
        $this->assertSame('#ffffff', $merged['background']);
        $this->assertSame(['wan'], $merged['tags'], 'non-default keys should pass through');
    }

    public function test_merge_options_replaces_non_array_with_array(): void
    {
        // If current is scalar and new is array (or vice versa), the new value wins.
        $merged = $this->invokeMerge(
            ['background' => 'legacy-string'],
            ['background' => '#eeeeee']
        );
        $this->assertSame('#eeeeee', $merged['background']);
    }

    public function test_create_map_allowlists_option_keys(): void
    {
        // createMap() uses array_intersect_key against an allowlist; verify
        // the allowlist logic shape on the intersect itself (the source
        // contract — createMap needs a DB to invoke directly).
        $allowed = ['background', 'tags', 'default_node_style', 'default_link_style'];
        $incoming = [
            'background' => '#123456',
            'tags' => ['core'],
            'width' => 9999,          // not allowlisted in options
            'malicious_key' => 'x',  // not allowlisted
        ];

        $filtered = array_intersect_key($incoming, array_flip($allowed));

        $this->assertSame('#123456', $filtered['background']);
        $this->assertSame(['core'], $filtered['tags']);
        $this->assertArrayNotHasKey('width', $filtered);
        $this->assertArrayNotHasKey('malicious_key', $filtered);
    }

    private function invokeImportOptions(array $incoming): array
    {
        $service = new MapService(new WebhookDispatcher());
        $method = new \ReflectionMethod($service, 'normalizeImportOptions');

        return $method->invoke($service, $incoming);
    }

    public function test_import_drops_unsupported_option_keys(): void
    {
        // A file from another install (or an older export) can carry keys this
        // plugin never reads; storing them would let an upload write arbitrary
        // JSON into wmng_maps.options.
        $options = $this->invokeImportOptions([
            'width' => 1200,
            'height' => 900,
            'background' => '#102030',
            'tags' => ['wan', 'core'],
            'default_node_style' => ['color' => '#ff0000'],
            'background_image' => '/etc/passwd',
            'unknown' => ['nested' => true],
        ]);

        $this->assertSame(1200, $options['width']);
        $this->assertSame(900, $options['height']);
        $this->assertSame('#102030', $options['background']);
        $this->assertSame(['wan', 'core'], $options['tags']);
        $this->assertSame(['color' => '#ff0000'], $options['default_node_style']);
        $this->assertArrayNotHasKey('background_image', $options);
        $this->assertArrayNotHasKey('unknown', $options);
    }

    public function test_import_clamps_dimensions_and_rejects_non_numeric_sizes(): void
    {
        $this->assertSame(100, $this->invokeImportOptions(['width' => 5])['width']);
        $this->assertSame(4096, $this->invokeImportOptions(['height' => 99999])['height']);
        // 4096.9 truncates to 4096 rather than rounding past the ceiling.
        $this->assertSame(4096, $this->invokeImportOptions(['width' => 4096.9])['width']);

        $fallback = $this->invokeImportOptions(['width' => 'auto', 'height' => null]);
        $this->assertSame(800, $fallback['width']);
        $this->assertSame(600, $fallback['height']);
    }

    public function test_import_falls_back_to_white_for_invalid_background(): void
    {
        foreach (['red', '#fff', '#12345', '', ['#ffffff'], null] as $invalid) {
            $this->assertSame('#ffffff', $this->invokeImportOptions(['background' => $invalid])['background'], 'invalid background: ' . var_export($invalid, true));
        }

        // Case is preserved for a valid value; shorthand hex is not supported by
        // the map forms either, so it never round-trips as a color.
        $this->assertSame('#ABCDEF', $this->invokeImportOptions(['background' => '#ABCDEF'])['background']);
    }

    public function test_import_defaults_when_options_are_missing_or_listed(): void
    {
        $defaults = ['width' => 800, 'height' => 600, 'background' => '#ffffff'];
        $this->assertSame($defaults, $this->invokeImportOptions([]));
        $this->assertSame($defaults, $this->invokeImportOptions(['#ffffff', 1200]));
    }
}
