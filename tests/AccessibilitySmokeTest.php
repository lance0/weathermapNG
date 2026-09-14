<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Accessibility smoke test — catches the obvious regressions the roadmap
 * asks for (ROADMAP.md "Validation coverage"):
 *
 *  - icon-only anchors and buttons must have aria-labels
 *  - outline must not be globally removed (keyboard focus visibility)
 *  - interactive elements must be real buttons/links with accessible names
 *  - form inputs must have associated labels or their own aria-label
 *  - the embed document must declare a language
 *  - canvas maps must expose a text alternative
 *
 * These are string assertions over the view source rather than a rendered
 * browser audit, so they run in plain PHPUnit on CI with no browser.
 * index/editor extend layouts.librenmsv1, so document lang is set by the
 * parent layout; only embed (a standalone page) is checked here.
 */
class AccessibilitySmokeTest extends TestCase
{
    private function source(string $page): string
    {
        return match ($page) {
            'index' => file_get_contents(__DIR__ . '/../resources/views/index.blade.php'),
            'editor' => editor_source(),
            'embed' => embed_source(),
            default => throw new \InvalidArgumentException("unknown page $page"),
        };
    }

    /** @dataProvider pageProvider */
    public function test_no_global_outline_removal(string $page): void
    {
        $content = $this->source($page);
        // Broad focus outline removals hurt keyboard navigation.
        $this->assertDoesNotMatchRegularExpression(
            '/\*\s*\{[^}]*outline\s*:\s*none/i',
            $content,
            "$page should not strip outlines from all elements"
        );
    }

    public function pageProvider(): array
    {
        return [
            ['index'],
            ['editor'],
            ['embed'],
        ];
    }

    /** @dataProvider pageProvider */
    public function test_buttons_have_accessible_names_or_text(string $page): void
    {
        $content = $this->source($page);

        foreach ($this->openingTags($content, 'button') as $i => [$tag, $inner, $full]) {
            $named = preg_match('/aria-label(?:ledby)?=/i', $tag) || trim($this->textOnly($inner)) !== '';
            $this->assertTrue(
                (bool) $named,
                "$page button #$i lacks aria-label/labelledby or visible text: " . trim($tag)
            );
        }
    }

    public function test_icon_only_controls_have_aria_labels(): void
    {
        // Rule: for each <a> or <button> opening tag, capture the full
        // element body. If the body has NO visible text (after stripping
        // tags) AND contains a Font Awesome icon span, the control must
        // carry aria-label on its opening tag.
        $sources = [
            'index' => file_get_contents(__DIR__ . '/../resources/views/index.blade.php'),
            'embed' => file_get_contents(__DIR__ . '/../resources/views/embed.blade.php'),
        ];

        $checked = 0;
        foreach ($sources as $page => $content) {
            foreach (['a', 'button'] as $el) {
                foreach ($this->openingTags($content, $el) as $i => [$tag, $inner, $full]) {
                    if (!preg_match('/\bfa[b"\']|\bfa\b|\bfas\b|\bfar\b|\bfab\b/', $inner)) {
                        continue; // no icon inside — skip
                    }
                    if (trim($this->textOnly($inner)) !== '') {
                        continue; // has visible text — no aria-label needed
                    }
                    $checked++;
                    $this->assertMatchesRegularExpression(
                        '/aria-label(?:ledby)?=/i',
                        $tag,
                        "$page icon-only $el #$i missing aria-label: " . trim($tag)
                    );
                }
            }
        }
        $this->assertGreaterThan(0, $checked, 'expected at least one icon-only control across views');
    }

    public function test_form_inputs_have_labels(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/index.blade.php');

        $this->assertGreaterThan(
            0,
            preg_match_all('/<(?:input|select|textarea)\b[^>]*?\bid="([^"]+)"[^>]*?>(?:.*?<\/(?:select|textarea)>|\s*\/?>)/is', $content, $m, PREG_SET_ORDER),
            'index should have labelled form inputs'
        );

        foreach ($m as $i => [1 => $id]) {
            $quality = preg_match('/<label[^>]*\bfor="' . preg_quote($id, '/') . '"/is', $content)
                || preg_match('/\b(aria-label(?:ledby)?=)/i', $m[$i][0]);
            $this->assertTrue(
                (bool) $quality,
                "index input #$id has neither <label for> nor aria-label: " . trim($m[$i][0])
            );
        }
    }

    public function test_canvas_has_accessible_fallback(): void
    {
        // The embed canvas is the primary interactive surface; give it a
        // programmatic description. The editor canvas is paired with the
        // properties sidebar text, so only require the embed one here.
        $embed = file_get_contents(__DIR__ . '/../resources/views/embed.blade.php');
        $this->assertMatchesRegularExpression(
            '/<canvas[^>]*id="map-canvas"[^>]*aria-label=|<canvas[^>]*aria-label=[^>]*id="map-canvas"/i',
            $embed,
            'embed map-canvas should carry an aria-label describing the map'
        );
    }

    public function test_embed_declares_language(): void
    {
        $src = file_get_contents(__DIR__ . '/../resources/views/embed.blade.php');
        $this->assertMatchesRegularExpression(
            '/<html[^>]*\blang="en"/i',
            $src,
            'embed.blade.php should declare its document language'
        );
    }

    public function test_index_and_editor_inherit_librenms_layout(): void
    {
        // These views rely on the parent LibreNMS layout for lang/meta; they
        // must not silently drop that inheritance.
        foreach (['index', 'editor'] as $page) {
            $src = file_get_contents(__DIR__ . '/../resources/views/' . $page . '.blade.php');
            $this->assertMatchesRegularExpression(
                "/@extends\\('layouts\\.librenmsv1'\\)/",
                $src,
                "$page should extend the LibreNMS layout (which declares lang)"
            );
        }
    }

    public function test_modals_use_role_dialog(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/index.blade.php');
        preg_match_all('/<div[^>]*class="[^"]*modal[^"]*"[^>]*role="dialog"[^>]*>/i', $content, $m);
        $this->assertNotEmpty($m[0], 'index modals should use role="dialog"');
    }

    /**
     * Yield every element by name: [openingTag, innerHtml, wholeSnippet].
     *
     * Blade markup ({{ ... }}) and @-directives are removed first — a `>`
     * inside `{{ $map->id }}` otherwise truncates the opening tag, making
     * the "inner text" start with stray Blade chunks and misclassify every
     * tagged control. Tag parsing spans multiple lines.
     *
     * @return iterable<int, array{string, string, string}>
     */
    private function openingTags(string $content, string $element): iterable
    {
        $content = preg_replace('/\{\{.*?\}\}/', '', $content) ?? $content;
        $content = preg_replace('/@[a-z]+/i', '', $content) ?? $content;

        $open = '/<' . $element . '\b[^>]*?>/is';
        $close = '</' . $element . '>';
        if (!preg_match_all($open, $content, $tags, PREG_OFFSET_CAPTURE)) {
            return;
        }
        foreach ($tags[0] as [$tag, $offset]) {
            $bodyStart = $offset + strlen($tag);
            $end = stripos($content, $close, $bodyStart);
            $inner = $end === false ? '' : substr($content, $bodyStart, $end - $bodyStart);
            $full = $end === false
                ? $tag
                : substr($content, $offset, $end - $offset + strlen($close));
            yield [$tag, $inner, $full];
        }
    }

    private function textOnly(string $html): string
    {
        return trim(strip_tags($html));
    }
}
