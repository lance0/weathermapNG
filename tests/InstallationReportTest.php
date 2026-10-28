<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\Services\InstallationReport;
use PHPUnit\Framework\TestCase;

class InstallationReportTest extends TestCase
{
    public function test_missing_runtime_is_unknown_and_report_does_not_repair_or_disclose_config(): void
    {
        $dir = sys_get_temp_dir() . '/wmng-report-' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/composer.json', json_encode(['require' => ['librenms/weathermapng' => '*'], 'secret' => 'do-not-disclose']));
        file_put_contents($dir . '/VERSION', '1.14.1');
        try {
            $report = InstallationReport::collect(null, $dir, $dir);
            $this->assertFalse($report['ok']);
            $this->assertTrue($report['checks']['composer_declaration']['ok']);
            $this->assertFalse($report['checks']['persistent_registry']['ok']);
            $this->assertNull($report['counts']['maps']);
            $this->assertFalse($report['checks']['database']['ok']);
            $this->assertStringContainsString('connectivity', $report['checks']['table_maps']['recovery']);
            $this->assertStringContainsString(escapeshellarg($dir), $report['checks']['persistent_registry']['recovery']);
            $this->assertNull($report['authentication']['embed_token_configured']);
            $this->assertDirectoryDoesNotExist($dir . '/output');
            $this->assertStringNotContainsString('do-not-disclose', json_encode($report));
        } finally {
            unlink($dir . '/composer.json');
            unlink($dir . '/VERSION');
            rmdir($dir);
        }
    }
    public function test_cli_missing_bootstrap_returns_safe_json_and_nonzero(): void
    {
        $process = new \Symfony\Component\Process\Process(
            [PHP_BINARY, dirname(__DIR__) . '/verify-deployment.php', '--json'],
            null,
            ['LIBRENMS_PATH' => sys_get_temp_dir() . '/wmng-missing-' . bin2hex(random_bytes(6))]
        );
        $this->assertSame(1, $process->run());
        $this->assertSame('', $process->getErrorOutput());
        $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($report['ok']);
        $this->assertNull($report['counts']['maps']);
    }

    public function test_cli_discards_bootstrap_output_and_exception_details(): void
    {
        $dir = sys_get_temp_dir() . '/wmng-bootstrap-' . bin2hex(random_bytes(6));
        mkdir($dir . '/vendor', 0755, true);
        mkdir($dir . '/bootstrap');
        file_put_contents($dir . '/vendor/autoload.php', '<?php echo "do-not-disclose-output";');
        file_put_contents($dir . '/bootstrap/app.php', '<?php throw new RuntimeException("do-not-disclose-exception");');
        try {
            $process = new \Symfony\Component\Process\Process(
                [PHP_BINARY, dirname(__DIR__) . '/verify-deployment.php', '--json'], null, ['LIBRENMS_PATH' => $dir]
            );
            $this->assertSame(1, $process->run());
            $this->assertStringNotContainsString('do-not-disclose', $process->getOutput() . $process->getErrorOutput());
            $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertFalse($report['checks']['bootstrap']['ok']);
        } finally {
            unlink($dir . '/vendor/autoload.php');
            unlink($dir . '/bootstrap/app.php');
            rmdir($dir . '/vendor');
            rmdir($dir . '/bootstrap');
            rmdir($dir);
        }
    }
}
