<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class LegacyBackupCommandTest extends TestCase
{
    public function testLegacyCommandsRefuseBeforeBootstrapOrWrites(): void
    {
        $directory = sys_get_temp_dir().'/wmng-backup-'.bin2hex(random_bytes(6));
        mkdir($directory.'/bin', 0700, true);
        mkdir($directory.'/backups', 0700);
        copy(__DIR__.'/../bin/backup.php', $directory.'/bin/backup.php');
        $saved = '{"existing":"must survive"}';
        file_put_contents($directory.'/backups/existing.json', $saved);
        try {
            foreach (['create', 'restore'] as $command) {
                // No LibreNMS bootstrap exists in this isolated copy.
                $process = new Process([PHP_BINARY, $directory.'/bin/backup.php', $command, $directory.'/backups/existing.json'], $directory);
                $process->run();
                self::assertSame(1, $process->getExitCode());
                self::assertStringContainsString('#backup-and-recovery', $process->getErrorOutput());
                self::assertStringNotContainsString('Failed opening', $process->getErrorOutput());
                self::assertSame(['existing.json'], array_values(array_diff(scandir($directory.'/backups'), ['.', '..'])));
                self::assertSame($saved, file_get_contents($directory.'/backups/existing.json'));
                self::assertSame(['backup.php'], array_values(array_diff(scandir($directory.'/bin'), ['.', '..'])));
                self::assertSame(file_get_contents(__DIR__.'/../bin/backup.php'), file_get_contents($directory.'/bin/backup.php'));
                self::assertSame(['backups', 'bin'], array_values(array_diff(scandir($directory), ['.', '..'])));
            }
        } finally {
            unlink($directory.'/backups/existing.json');
            unlink($directory.'/bin/backup.php');
            rmdir($directory.'/backups');
            rmdir($directory.'/bin');
            rmdir($directory);
        }
    }
}
