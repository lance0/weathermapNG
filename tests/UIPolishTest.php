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

    public function test_blank_target_links_in_blade_views_use_noopener(): void
    {
        foreach ($this->bladeViewFiles() as $file) {
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

    public function test_librenms_hook_and_legacy_views_follow_button_conventions(): void
    {
        $hookPage = file_get_contents(__DIR__ . '/../resources/views/hooks/page.blade.php');
        $legacyPage = file_get_contents(__DIR__ . '/../resources/views/page.blade.php');
        $mapPage = file_get_contents(__DIR__ . '/../resources/views/map.blade.php');

        $this->assertStringContainsString('class="btn btn-success btn-sm pull-right"', $hookPage);
        $this->assertStringContainsString("url('plugin/WeathermapNG/editor/' . \$map->id)", $hookPage);
        $this->assertStringContainsString('class="btn btn-sm btn-primary" aria-label="Edit map', $hookPage);
        $this->assertStringContainsString('class="btn btn-sm btn-default" aria-label="View map', $hookPage);

        $this->assertStringContainsString('type="button" class="btn btn-success btn-sm"', $legacyPage);
        $this->assertStringContainsString('type="submit" class="btn btn-success"', $legacyPage);
        $this->assertStringContainsString('class="btn btn-danger" title="Delete" aria-label="Delete map', $legacyPage);
        $this->assertStringContainsString('id="legacyDeleteMapModal"', $legacyPage);
        $this->assertStringContainsString('id="confirmLegacyDeleteMapBtn"', $legacyPage);
        $this->assertStringContainsString("$('#legacyDeleteMapModal').modal('show');", $legacyPage);
        $this->assertStringContainsString('showPageAlert(', $legacyPage);
        $this->assertStringNotContainsString('alert(\'Error', $legacyPage);
        $this->assertStringNotContainsString('confirm(', $legacyPage);
        $this->assertStringNotContainsString('alert(', $legacyPage);

        $this->assertStringContainsString('Open Live Map', $mapPage);
        $this->assertStringContainsString('class="btn btn-default"', $mapPage);
    }

    public function test_close_buttons_and_legacy_map_copy_are_polished(): void
    {
        foreach ($this->bladeViewFiles() as $file) {
            $content = file_get_contents(__DIR__ . '/../' . $file);
            preg_match_all('/<button\b(?=[^>]*class="[^"]*\bclose\b)[^>]*>/', $content, $matches);

            foreach ($matches[0] as $button) {
                $this->assertStringContainsString('type="button"', $button, "$file has a close control without an explicit button type");
                $this->assertStringContainsString('aria-label="Close"', $button, "$file has an unnamed close control: $button");
            }
        }

        $legacyMap = file_get_contents(__DIR__ . '/../resources/views/map.blade.php');
        $this->assertStringNotContainsString('live rendering functionality is not yet implemented', $legacyMap);
        $this->assertStringNotContainsString('Rendering engine coming soon', $legacyMap);
        $this->assertStringContainsString('Use the live map view for current rendering and traffic data.', $legacyMap);
    }

    public function test_settings_page_uses_inline_feedback_and_confirmation_modal(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/settings.blade.php');

        $this->assertStringContainsString('id="settings-alerts"', $content);
        $this->assertStringContainsString('id="settingsConfirmModal"', $content);
        $this->assertStringContainsString('function showSettingsAlert(', $content);
        $this->assertStringContainsString('function showSettingsConfirm(', $content);
        $this->assertStringContainsString("pre.textContent = JSON.stringify(settings, null, 2);", $content);
        $this->assertStringContainsString("showSettingsAlert('Restore from backup is not available", $content);
        $this->assertStringContainsString('class="btn btn-success" onclick="createBackup()"', $content);
        $this->assertStringContainsString("showSettingsAlert('Settings reset to defaults.', 'success');", $content);
        $this->assertStringNotContainsString('confirm(', $content);
        $this->assertStringNotContainsString('alert(', $content);
        $this->assertStringNotContainsString('Restore functionality would be implemented here', $content);
        $this->assertStringNotContainsString("'<pre>' + JSON.stringify", $content);
    }

    public function test_active_index_and_editor_use_bootstrap_confirmation_modals(): void
    {
        $index = file_get_contents(__DIR__ . '/../resources/views/index.blade.php');
        $editor = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');

        $this->assertStringContainsString('id="deleteMapModal"', $index);
        $this->assertStringContainsString('id="confirmDeleteMapBtn"', $index);
        $this->assertStringContainsString("$('#deleteMapModal').modal('show');", $index);
        $this->assertStringContainsString('form.submit();', $index);
        $this->assertStringNotContainsString('confirm(', $index);
        $this->assertStringNotContainsString('alert(', $index);

        $this->assertStringContainsString('id="editorConfirmModal"', $editor);
        $this->assertStringContainsString('function showEditorConfirm(', $editor);
        $this->assertStringContainsString('pendingEditorCancelAction', $editor);
        $this->assertStringContainsString('Resize Canvas', $editor);
        $this->assertStringContainsString('Delete Node', $editor);
        $this->assertStringContainsString('Delete Link', $editor);
        $this->assertStringContainsString('Restore Version', $editor);
        $this->assertStringContainsString('Clear Old Versions', $editor);
        $this->assertStringContainsString("WMNGToast.error('Failed to save node:", $editor);
        $this->assertStringNotContainsString('confirm(', $editor);
        $this->assertStringNotContainsString('alert(', $editor);
    }

    public function test_standalone_versioning_and_canvas_scripts_avoid_browser_prompts(): void
    {
        foreach ([
            'resources/js/versioning.js',
            'resources/js/weathermapng.js',
            'js/weathermapng.js',
        ] as $file) {
            $content = file_get_contents(__DIR__ . '/../' . $file);

            $this->assertStringNotContainsString('confirm(', $content, "$file should avoid native confirmation prompts");
            $this->assertStringNotContainsString('alert(', $content, "$file should avoid native alert prompts");
            $this->assertStringNotContainsString('console.log', $content, "$file should avoid debug logging");
        }

        $versioning = file_get_contents(__DIR__ . '/../resources/js/versioning.js');
        $this->assertStringContainsString('id = \'versionDecisionModal\'', $versioning);
        $this->assertStringContainsString("WMNGToast.info('Version comparison is not available yet.'", $versioning);
        $this->assertStringContainsString("WMNGToast.info('Version details are not available yet.'", $versioning);
        $this->assertStringContainsString('body: formData', $versioning);
        $this->assertMatchesRegularExpression(
            "/method: 'POST',\n\\s+headers: \\{\n\\s+'X-CSRF-TOKEN'/",
            $versioning
        );
    }

    public function test_roadmap_tracks_current_stable_release_and_recent_polish(): void
    {
        $content = file_get_contents(__DIR__ . '/../ROADMAP.md');

        $this->assertStringContainsString('## Current Status: v1.6.5 (Stable)', $content);
        $this->assertStringContainsString('Idempotent LibreNMS plugin registration cleanup during reinstall', $content);
        $this->assertStringContainsString('### v1.6.5', $content);
        $this->assertStringContainsString('Duplicate inactive `WeathermapNG` rows are cleaned', $content);
        $this->assertStringContainsString('Legacy map list delete action uses Bootstrap confirmation', $content);
    }

    /**
     * @return list<string>
     */
    private function bladeViewFiles(): array
    {
        $root = realpath(__DIR__ . '/../resources/views');
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = 'resources/views/' . substr($file->getPathname(), strlen($root) + 1);
            }
        }

        sort($files);

        return $files;
    }
}
