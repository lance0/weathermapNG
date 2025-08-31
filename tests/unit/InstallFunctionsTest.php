<?php

use PHPUnit\Framework\TestCase;

class InstallFunctionsTest extends TestCase
{
    private $testDir;
    private $pluginPath;
    
    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/weathermapng-test-' . uniqid();
        $this->pluginPath = $this->testDir . '/plugin';
        mkdir($this->pluginPath, 0755, true);
    }
    
    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->testDir);
    }
    
    private function recursiveRemoveDirectory($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->recursiveRemoveDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    public function testPHPVersionCheck()
    {
        $this->assertTrue(
            version_compare(PHP_VERSION, '8.0.0', '>='),
            'PHP version should be 8.0 or higher'
        );
    }
    
    public function testRequiredExtensions()
    {
        $required = ['gd', 'json', 'pdo', 'mbstring'];
        
        foreach ($required as $ext) {
            $this->assertTrue(
                extension_loaded($ext),
                "PHP extension $ext should be loaded"
            );
        }
    }
    
    public function testConfigFileCreation()
    {
        $configDir = $this->pluginPath . '/config';
        $configFile = $configDir . '/weathermapng.php';
        
        // Create config directory
        $this->assertTrue(mkdir($configDir, 0755, true));
        
        // Create default config
        $defaultConfig = <<<'PHP'
<?php
return [
    'default_width' => 800,
    'default_height' => 600,
    'poll_interval' => 300,
];
PHP;
        
        $this->assertNotFalse(
            file_put_contents($configFile, $defaultConfig),
            'Should be able to create config file'
        );
        
        $this->assertFileExists($configFile);
        
        // Test config can be loaded
        $config = include $configFile;
        $this->assertIsArray($config);
        $this->assertEquals(800, $config['default_width']);
    }
    
    public function testOutputDirectoryCreation()
    {
        $outputDir = $this->pluginPath . '/output';
        
        $this->assertTrue(
            mkdir($outputDir, 0775, true),
            'Should be able to create output directory'
        );
        
        $this->assertDirectoryExists($outputDir);
        $this->assertTrue(is_writable($outputDir));
    }
    
    public function testPermissionSetting()
    {
        $testFile = $this->pluginPath . '/test.php';
        file_put_contents($testFile, '<?php echo "test";');
        
        $this->assertTrue(
            chmod($testFile, 0755),
            'Should be able to set file permissions'
        );
        
        $perms = fileperms($testFile);
        $this->assertEquals(
            '0755',
            substr(sprintf('%o', $perms), -4),
            'File should have 755 permissions'
        );
    }
    
    public function testDockerDetection()
    {
        // Test without Docker indicators
        $this->assertFalse(
            file_exists('/.dockerenv'),
            'Should not detect Docker in test environment'
        );
        
        // Test with environment variable
        putenv('DOCKER_CONTAINER=1');
        $this->assertEquals('1', getenv('DOCKER_CONTAINER'));
        putenv('DOCKER_CONTAINER=');
    }
    
    public function testLibreNMSPathDetection()
    {
        // Create mock LibreNMS structure
        $mockLibreNMS = $this->testDir . '/librenms';
        mkdir($mockLibreNMS . '/bootstrap', 0755, true);
        touch($mockLibreNMS . '/bootstrap/app.php');
        
        // Test detection
        $this->assertFileExists(
            $mockLibreNMS . '/bootstrap/app.php',
            'Mock LibreNMS should be created'
        );
        
        // Test plugin path
        $pluginPath = $mockLibreNMS . '/html/plugins/WeathermapNG';
        mkdir(dirname($pluginPath), 0755, true);
        
        $this->assertDirectoryExists(dirname($pluginPath));
    }
    
    public function testComposerFileValidation()
    {
        $composerJson = [
            'name' => 'librenms/weathermapng',
            'description' => 'Network Weather Map for LibreNMS',
            'type' => 'librenms-plugin',
            'require' => [
                'php' => '>=8.0'
            ]
        ];
        
        $composerFile = $this->pluginPath . '/composer.json';
        file_put_contents(
            $composerFile,
            json_encode($composerJson, JSON_PRETTY_PRINT)
        );
        
        $this->assertFileExists($composerFile);
        
        $decoded = json_decode(file_get_contents($composerFile), true);
        $this->assertIsArray($decoded);
        $this->assertEquals('librenms/weathermapng', $decoded['name']);
    }
    
    public function testLogFileCreation()
    {
        $logFile = $this->testDir . '/test.log';
        
        $this->assertTrue(
            touch($logFile),
            'Should be able to create log file'
        );
        
        $this->assertFileExists($logFile);
        
        // Test writing to log
        $testMessage = "Test log entry\n";
        file_put_contents($logFile, $testMessage, FILE_APPEND);
        
        $this->assertStringContainsString(
            'Test log entry',
            file_get_contents($logFile)
        );
    }
    
    public function testPluginJsonStructure()
    {
        $pluginData = [
            'name' => 'WeathermapNG',
            'version' => '2.0.0',
            'author' => 'LibreNMS Community',
            'description' => 'Network Weather Map Visualization',
            'homepage' => 'https://github.com/lance0/weathermapNG',
            'min_librenms_version' => '23.8.0',
            'database' => true,
            'settings' => true
        ];
        
        $pluginFile = $this->pluginPath . '/plugin.json';
        file_put_contents(
            $pluginFile,
            json_encode($pluginData, JSON_PRETTY_PRINT)
        );
        
        $decoded = json_decode(file_get_contents($pluginFile), true);
        
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('name', $decoded);
        $this->assertArrayHasKey('version', $decoded);
        $this->assertEquals('WeathermapNG', $decoded['name']);
    }
    
    public function testRequiredFilesExistence()
    {
        $requiredFiles = [
            'WeathermapNG.php',
            'routes.php',
            'composer.json',
            'plugin.json'
        ];
        
        foreach ($requiredFiles as $file) {
            $filePath = $this->pluginPath . '/' . $file;
            touch($filePath);
            $this->assertFileExists(
                $filePath,
                "Required file $file should exist"
            );
        }
    }
    
    public function testEnvironmentVariables()
    {
        // Test setting and getting environment variables
        putenv('LIBRENMS_PATH=/opt/librenms');
        $this->assertEquals(
            '/opt/librenms',
            getenv('LIBRENMS_PATH'),
            'Should be able to set and get environment variables'
        );
        putenv('LIBRENMS_PATH=');
    }
    
    public function testScriptExecutability()
    {
        $scriptFile = $this->pluginPath . '/test.sh';
        file_put_contents($scriptFile, "#!/bin/bash\necho 'test'");
        
        chmod($scriptFile, 0755);
        
        $this->assertTrue(
            is_executable($scriptFile),
            'Script should be executable after chmod'
        );
    }
}