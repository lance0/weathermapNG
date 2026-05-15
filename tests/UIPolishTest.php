<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

class UIPolishTest extends TestCase
{
    public function test_embed_controls_do_not_contain_malformed_buttons_or_removed_focus(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/embed.blade.php');

        $this->assertStringNotContainsString('</nbutton>', $content);
        $this->assertStringNotContainsString("replaceAll('\\nbutton'", $content);
        $this->assertStringNotContainsString('outline:none', $content);
        $this->assertStringNotContainsString('🌊', $content);
        $this->assertStringNotContainsString('⚙️', $content);
    }

    public function test_embed_controls_have_button_types_and_accessible_names(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/embed.blade.php');

        foreach (['toggle-transport', 'toggle-flow', 'viz-settings', 'export-png'] as $id) {
            $this->assertMatchesRegularExpression(
                '/<button[^>]*type="button"[^>]*id="' . preg_quote($id, '/') . '"[^>]*aria-label="/',
                $content,
                "$id should be a named button"
            );
        }

        $this->assertStringContainsString("button.setAttribute('aria-label', label);", $content);
        $this->assertStringContainsString("button.className = 'btn btn-light btn-sm';", $content);
        $this->assertStringContainsString('class="btn btn-primary btn-sm"', $content);
        $this->assertStringContainsString('class="btn btn-light btn-sm"', $content);
        $this->assertStringContainsString("btn.classList.add('btn-secondary');", $content);
    }

    public function test_editor_toolbar_buttons_are_named_buttons(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
        preg_match_all('/<button\b(?=[^>]*class="tool-btn")[^>]*>/', $content, $matches);

        $this->assertNotEmpty($matches[0], 'Expected editor tool buttons');

        foreach ($matches[0] as $button) {
            $this->assertStringContainsString('type="button"', $button);
            $this->assertStringContainsString('aria-label=', $button);
        }
    }

    public function test_editor_theme_detection_has_no_debug_logging_or_head_observer(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
        $indexContent = file_get_contents(__DIR__ . '/../resources/views/index.blade.php');

        $this->assertStringNotContainsString('console.log', $content);
        $this->assertStringNotContainsString('observer.observe(document.head', $content);
        $this->assertStringNotContainsString('observer.observe(document.head', $indexContent);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $content);
        $this->assertStringContainsString('.tool-btn.link-active { animation: none; }', $content);
    }

    public function test_blank_target_links_in_touched_views_use_noopener(): void
    {
        foreach (['resources/views/editor.blade.php', 'resources/views/index.blade.php'] as $file) {
            $content = file_get_contents(__DIR__ . '/../' . $file);
            $blankTargetAnchors = [];
            $offset = 0;
            while (($targetPosition = strpos($content, 'target="_blank"', $offset)) !== false) {
                $anchorStart = strrpos(substr($content, 0, $targetPosition), '<a');
                $anchorEnd = strpos($content, '>', $targetPosition);
                $this->assertNotFalse($anchorStart, "$file has target blank outside an anchor");
                $this->assertNotFalse($anchorEnd, "$file has an unterminated target blank anchor");
                $blankTargetAnchors[] = substr($content, $anchorStart, $anchorEnd - $anchorStart + 1);
                $offset = $targetPosition + strlen('target="_blank"');
            }

            $this->assertNotEmpty($blankTargetAnchors, "$file should contain at least one blank target link for this check");

            foreach ($blankTargetAnchors as $anchor) {
                $this->assertStringContainsString('rel="noopener noreferrer"', $anchor, "$file has unsafe blank target link: $anchor");
            }
        }
    }

    public function test_template_cards_use_button_semantics_without_nested_button(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/index.blade.php');

        $this->assertStringContainsString('<button type="button" class="template-card"', $content);
        $this->assertStringContainsString('aria-label="Use template', $content);
        $this->assertStringContainsString('btn btn-success btn-sm template-card-btn', $content);
        $this->assertStringNotContainsString('<div class="template-card-icon">', $content);
        $this->assertStringNotContainsString('<div class="template-card-title">', $content);
        $this->assertStringNotContainsString('<div class="template-card-desc">', $content);
        $this->assertStringNotContainsString('<div class="template-card-meta">', $content);
        $this->assertStringNotContainsString('<div class="template-card" onclick=', $content);
        $this->assertStringNotContainsString('event.stopPropagation(); selectTemplate', $content);
    }
}
