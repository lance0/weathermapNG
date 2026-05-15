<?php

use LibreNMS\Plugins\WeathermapNG\WeathermapNG;
use PHPUnit\Framework\TestCase;

class VersionMetadataTest extends TestCase
{
    public function testVersionFileIsSemver(): void
    {
        $version = trim(file_get_contents(__DIR__ . '/../VERSION'));

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version);
    }

    public function testComposerVersionMatchesVersionFile(): void
    {
        $version = trim(file_get_contents(__DIR__ . '/../VERSION'));
        $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

        $this->assertSame($version, $composer['version']);
    }

    public function testChangelogContainsCurrentVersion(): void
    {
        $version = trim(file_get_contents(__DIR__ . '/../VERSION'));
        $changelog = file_get_contents(__DIR__ . '/../CHANGELOG.md');

        $this->assertStringContainsString("## [{$version}]", $changelog);
    }

    public function testRuntimeVersionUsesVersionFile(): void
    {
        $version = trim(file_get_contents(__DIR__ . '/../VERSION'));

        $this->assertSame($version, (new WeathermapNG())->getVersion());
    }
}
