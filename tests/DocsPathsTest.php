<?php

use PHPUnit\Framework\TestCase;

class DocsPathsTest extends TestCase
{
    private $docs = [
        'README.md',
        'API.md',
        'FAQ.md',
        'INSTALL.md',
        'DEPLOYMENT.md',
    ];

    public function testDocsUseV2Paths()
    {
        foreach ($this->docs as $file) {
            $this->assertFileExists($file, "$file should exist");
            $content = file_get_contents($file);
            $this->assertStringContainsString('plugin/WeathermapNG', $content, "$file should reference v2 path");
            $this->assertStringNotContainsString('plugins/weathermapng', $content, "$file should not reference legacy path");
        }
    }

    public function testTemplatesPreferV2Paths()
    {
        $files = [
            'Resources/views/embed.blade.php',
            'Resources/views/index.blade.php',
            'Resources/views/hooks/page.blade.php',
            'Resources/views/hooks/device-overview.blade.php',
            'Resources/views/hooks/port-tab.blade.php',
            'Resources/views/weathermapng/page.blade.php',
        ];

        foreach ($files as $file) {
            $this->assertFileExists($file, "$file should exist");
            $content = file_get_contents($file);
            $this->assertStringNotContainsString('/plugins/weathermapng', $content, "$file contains legacy path");
        }
    }
}

