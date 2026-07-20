<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\NodeLabelNormalizer;

class NodeLabelNormalizerTest extends TestCase
{
    public function test_normalize_strips_html_tags(): void
    {
        $this->assertSame('Router-1', NodeLabelNormalizer::normalize('<img src=x onerror=alert(1)>Router-1'));
    }

    public function test_normalize_trims_whitespace(): void
    {
        $this->assertSame('Core', NodeLabelNormalizer::normalize('  Core  '));
    }

    public function test_normalize_strips_tags_and_trims_together(): void
    {
        $this->assertSame('Core', NodeLabelNormalizer::normalize('  <b>Core</b>  '));
    }

    public function test_normalize_keeps_special_chars_raw(): void
    {
        // Quotes/ampersand are left raw — render-time escaping (escapeHtml/
        // textContent) handles them, not the normalizer.
        $this->assertSame('Router "Core" & ', NodeLabelNormalizer::normalize('Router "Core" & <Main>'));
    }

    public function test_normalize_preserves_normal_text(): void
    {
        $this->assertSame('Core-Router-01', NodeLabelNormalizer::normalize('Core-Router-01'));
    }

    public function test_normalize_preserves_unicode(): void
    {
        $this->assertSame('Routeur-Réseau', NodeLabelNormalizer::normalize('Routeur-Réseau'));
    }

    public function test_normalize_strips_nested_tags(): void
    {
        $this->assertSame('clean', NodeLabelNormalizer::normalize('<div><span><b>clean</b></span></div>'));
    }

    public function test_normalize_strips_script_tags(): void
    {
        $this->assertSame('document.cookieNode', NodeLabelNormalizer::normalize('<script>document.cookie</script>Node'));
    }

    public function test_normalize_returns_empty_for_html_only_input(): void
    {
        $this->assertSame('', NodeLabelNormalizer::normalize('<b></b>'));
    }

    public function test_normalize_returns_empty_for_empty_string(): void
    {
        $this->assertSame('', NodeLabelNormalizer::normalize(''));
    }

    public function test_normalize_returns_empty_for_whitespace_only(): void
    {
        $this->assertSame('', NodeLabelNormalizer::normalize('   '));
    }

    public function test_normalize_handles_null(): void
    {
        $this->assertSame('', NodeLabelNormalizer::normalize(null));
    }

    public function test_normalize_or_throw_returns_normalized_label(): void
    {
        $this->assertSame('Router-1', NodeLabelNormalizer::normalizeOrThrow('  <b>Router-1</b>  '));
    }

    public function test_normalize_or_throw_throws_on_html_only_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Node label must not be empty after removing HTML tags.');
        NodeLabelNormalizer::normalizeOrThrow('<b></b>');
    }

    public function test_normalize_or_throw_throws_on_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NodeLabelNormalizer::normalizeOrThrow('');
    }

    public function test_normalize_or_throw_throws_on_whitespace_only(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NodeLabelNormalizer::normalizeOrThrow('   ');
    }

    public function test_normalize_or_throw_throws_on_null(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NodeLabelNormalizer::normalizeOrThrow(null);
    }
}
