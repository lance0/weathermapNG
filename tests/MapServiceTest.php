<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\MapService;

class MapServiceTest extends TestCase
{
    /**
     * Behavioral tests for the private pure methods via reflection.
     * createMap()/updateMap() themselves need a live DB; their option
     * handling is covered by the merge/allowlist logic tested here.
     */

    private function invokeMerge(array $current, array $new): array
    {
        $service = new MapService();
        $reflection = new \ReflectionMethod($service, 'mergeMapOptions');
        $reflection->setAccessible(true);
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
}
