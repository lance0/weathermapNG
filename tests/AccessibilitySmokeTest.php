<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Accessibility smoke test — catches the obvious regressions the roadmap
 * asks for (ROADMAP.md "Validation coverage"):
 *
 *  - icon-only controls must have aria-labels
 *  - outline must not be globally removed (keyboard focus visibility)
 *  - interactive elements must be real buttons/links with accessible names
 *  - form inputs must have associated labels or their own aria-label
 *  - the embed document must declare a language
 *  - canvas maps must expose a text alternative
 *
 * These are string assertions over the view source rather than a rendered
 * browser audit, so they run in plain PHPUnit on CI with no browser.
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

        // Buttons may span multiple lines; balance is rendered by Blade so
        // child detection uses: does the opening tag carry a name, or does
        // the source right after it contain non-tag text before </button>.
        preg_match_all('/<button\b[^>]*?>/', $content, $buttons);
        $this->assertNotEmpty($buttons[0], "$page should contain buttons to check");

        foreach ($buttons[0] as $i => $tag) {
            $named = preg_match('/aria-label=|aria-labelledby=/', $tag);
            $named = $named || $this->buttonHasVisibleText($content, $tag);
            $this->assertTrue(
                $named,
                "$page button #$i lacks aria-label/labelledby or inner text: " . trim($tag)
            );
        }
    }

    private function buttonHasVisibleText(string $content, string $tag): bool
    {
        // Locate the button region after the tag and inside the closing
        // marker; a button with visible text (possibly plus aria-hidden
        // icons) qualifies as named even without aria-label.
        $offset = strpos($content, $tag);
        if ($offset === false) {
            return false;
        }
        $rest = substr($content, $offset + strlen($tag));
        $end = stripos($rest, '</button>');
        if ($end === false) {
            return false;
        }
        $inner = substr($rest, 0, $end);
        $visible = trim(strip_tags($inner));

        return $visible !== '';
    }

    public function test_icon_only_controls_have_aria_labels(): void
    {
        // Font Awesome icon-only anchors and buttons must be labelled — no
        // visible text exists for these. Blade source wraps tags across
        // lines, so capture each opening tag lazily up to its closing `>`
        // and evaluate the tag with /s so multi-line attributes count.
        $sources = [
            'index' => file_get_contents(__DIR__ . '/../resources/views/index.blade.php'),
            'embed' => file_get_contents(__DIR__ . '/../resources/views/embed.blade.php'),
        ];

        foreach ($sources as $page => $content) {
            preg_match_all(
                '/<(?:a|button)\b[^>]*?>\s*<i\s[^>]*aria-hidden="true"[^>]*>\s*<\/i>/is',
                $content,
                $iconOnly
            );

            foreach ($iconOnly[0] as $i => $snippet) {
                // Take only the tag portion (up to the first `>`), which
                // spans multiple lines for these controls.
                preg_match('/^<(?:a|button)\b.*?>/is', $snippet, $tagMatch);
                $tag = $tagMatch[0];
                $visible = $this->controlHasVisibleText($content, $snippet);
                $accessible = preg_match('/aria-label(?:ledby)?=/i', $tag) || $visible;
                $this->assertTrue(
                    (bool) $accessible,
                    "$page icon-only control #$i missing aria-label: " . trim($tag)
                );
            }
        }
    }

    private function controlHasVisibleText(string $content, string $snippet): bool
    {
        // A control followed by an aria-hidden icon may still have text
        // content further on; if so, it doesn't need an aria-label.
        $offset = strpos($content, $snippet);
        if ($offset === false) {
            return false;
        }
        $rest = substr($content, $offset + strlen($snippet));
        $end = min(
            stripos($rest, '</button>') === false ? PHP_INT_MAX : stripos($rest, '</button>'),
            stripos($rest, '</a>') === false ? PHP_INT_MAX : stripos($rest, '</a>')
        );
        if ($end === PHP_INT_MAX) {
            return false;
        }
        $inner = substr($rest, 0, $end);
        $visible = trim(strip_tags($inner));

        return $visible !== '';
    }

    public function test_form_inputs_have_labels(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/index.blade.php');

        preg_match_all('/<(?:input|select|textarea)\b[^>]*\bid="([^"]+)"[^>]*>/is', $content, $m, PREG_SET_ORDER);
        $this->assertGreaterThan(0, $m, 'index should have labelled form inputs');

        foreach ($m as [1 => $id, 0 => $tag]) {
            $hasLabelFor = preg_match('/<label[^>]*\bfor="' . preg_quote($id, '/') . '"/i', $content)
                || preg_match('/<label[^>]*\bfor="' . preg_quote($id, '/') . '"/is', $content);
            $hasOwnAria = preg_match('/aria-label(?:ledby)?=/i', $tag);
            $this->assertTrue(
                ($hasLabelFor && $hasOwnAria) || $hasOwnAria || (bool) $hasLabelFor,
                "index input #$id has neither <label for> nor aria-label: " . trim($tag)
            );
        }
    }

    public function test_canvas_has_accessible_fallback(): void
    {
        // The embed canvas is the primary interactive surface; give it a
        // programmatic description. The editor canvas is described by the
        // properties sidebar, so only require the embed one here.
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

    public function test_modals_use_role_dialog(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/index.blade.php');
        preg_match_all('/<div[^>]*class="[^"]*modal[^"]*"[^>]*role="dialog"/i', $content, $m);
        $this->assertNotEmpty($m[0], 'index modals should use role="dialog"');
    }
}
