<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

class UIEmbedKioskTest extends TestCase
{
    private string $content;

    protected function setUp(): void
    {
        parent::setUp();
        $this->content = file_get_contents(__DIR__ . '/../resources/views/embed.blade.php');
    }

    public function test_embed_passes_kiosk_config_to_js(): void
    {
        $this->assertStringContainsString("kioskEnabled: @json(\$kiosk)", $this->content);
        $this->assertStringContainsString("cycleSeconds: @json(\$cycleSeconds)", $this->content);
        $this->assertStringContainsString("linkTarget: @json(\$target)", $this->content);
        $this->assertStringContainsString("mapList: @json(\$mapList ?? [])", $this->content);
        $this->assertStringContainsString("mapData = @json(\$mapData ?? [])", $this->content);
        $this->assertStringContainsString("initialLive = @json(\$liveData ?? [])", $this->content);
    }

    public function test_embed_includes_kiosk_mode_styles(): void
    {
        $this->assertStringContainsString('body.kiosk-mode', $this->content);
        $this->assertStringContainsString('body.kiosk-mode.show-chrome', $this->content);
        $this->assertStringContainsString('.kiosk-exit', $this->content);
    }

    public function test_embed_includes_kiosk_cycle_logic(): void
    {
        $this->assertStringContainsString('function initKioskMode()', $this->content);
        $this->assertStringContainsString('if (!WMNG_CONFIG.kioskEnabled) return;', $this->content);
        $this->assertStringContainsString('window.setTimeout(() => { window.location.assign(nextUrl.toString()); }', $this->content);
    }

    public function test_embed_click_through_uses_configurable_target(): void
    {
        $this->assertStringContainsString("window.open(url, WMNG_CONFIG.linkTarget || '_blank');", $this->content);
        $this->assertStringContainsString("window.open(url, WMNG_CONFIG.linkTarget || '_blank');", $this->content);
    }

    public function test_embed_has_kiosk_exit_button(): void
    {
        $this->assertStringContainsString('id="kiosk-exit"', $this->content);
        $this->assertStringContainsString('aria-label="Exit kiosk mode"', $this->content);
    }

    public function test_embed_escape_keyhandler_does_not_show_chrome_before_toggle(): void
    {
        // The two listener pattern was: generic keydown => showChrome, then Escape handler toggles.
        // That meant Escape always ended with chrome hidden. The fix registers one keydown listener
        // where Escape clears the timer and toggles, and everything else calls showChrome().
        $this->assertSame(
            1,
            substr_count($this->content, "addEventListener('keydown'"),
            'Only one keydown listener should be registered in kiosk mode'
        );
        $this->assertMatchesRegularExpression(
            "/if \(e\.key === 'Escape'\) \{[^}]+body\.classList\.toggle\('show-chrome'\);[^}]+return;[^}]+\}\s+showChrome\(\);/s",
            $this->content,
            'Escape must toggle show-chrome without first calling showChrome'
        );
    }

    public function test_kiosk_exit_button_does_not_overlap_status_bar(): void
    {
        // The status bar is anchored bottom:10px; right:10px. The exit button must not share
        // that corner to avoid overlap when chrome is revealed.
        $this->assertStringContainsString('.kiosk-exit {', $this->content);
        $this->assertStringNotContainsString(
            '.kiosk-exit {'."\n".'            position: fixed;'."\n".'            bottom: 10px;'."\n".'            right: 10px;',
            $this->content,
            'kiosk-exit must avoid bottom:10px;right:10px status-bar corner'
        );
        preg_match('/\.kiosk-exit \{[^}]+bottom:\s*(\d+px);[^}]+\}/s', $this->content, $matches);
        $this->assertNotEmpty($matches, 'kiosk-exit must declare a bottom offset');
        $this->assertNotSame('10px', $matches[1], 'kiosk-exit bottom offset must differ from status-bar');
    }
}
