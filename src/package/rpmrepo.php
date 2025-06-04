<?php

namespace staticphp\package;

use staticphp\package;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class rpmrepo implements package
{
    private $moduleYaml;
    private $phpVersion;

    public function __construct(string $phpVersion = '8.4')
    {
        $this->moduleYaml = BASE_PATH . '/config/rpm.yaml';
        $this->phpVersion = $phpVersion;
    }

    public function getFpmConfig(): array
    {
        // Create a temporary directory for the repository files
        $repoDir = TEMP_DIR . '/repo';
        if (!file_exists($repoDir)) {
            mkdir($repoDir, 0755, true);
        }

        // Get the full PHP version from the binary
        $fullPhpVersion = $this->getFullPhpVersion();

        // Read the module YAML file
        $yamlContent = file_get_contents($this->moduleYaml);

        // Replace all placeholders in the YAML content
        $yamlContent = str_replace('majorminorpatch', $fullPhpVersion, $yamlContent);
        $yamlContent = str_replace('majorminor', $this->phpVersion, $yamlContent);
        $yamlContent = str_replace('iteration', '1', $yamlContent);

        // Get architecture
        $archProcess = new Process(['uname', '-m']);
        $archProcess->run();
        $architecture = trim($archProcess->getOutput());

        if (empty($architecture)) {
            // Try alternative method
            $archProcess = new Process(['arch']);
            $archProcess->run();
            $architecture = trim($archProcess->getOutput());

            if (empty($architecture)) {
                echo "Warning: Could not determine architecture, using x86_64 as fallback\n";
                $architecture = 'x86_64';
            }
        }

        $yamlContent = str_replace('architecture', $architecture, $yamlContent);

        // Parse the updated YAML content
        $moduleData = Yaml::parse($yamlContent);

        // Extract module information
        $moduleName = $moduleData['data']['name'] ?? 'static-php';
        $moduleStream = $moduleData['data']['stream'] ?? $this->phpVersion;
        $moduleVersion = $moduleData['data']['version'] ?? '20250603';

        // Create the repository directory structure
        $moduleDir = $repoDir . '/modules/' . $moduleName;
        if (!file_exists($moduleDir)) {
            mkdir($moduleDir, 0755, true);
        }

        // Create a simple repository configuration file
        $repoConfigFile = $repoDir . '/static-php.repo';
        $repoConfig = "[static-php]\n";
        $repoConfig .= "name=Static PHP Repository\n";
        $repoConfig .= "baseurl=file://" . DIST_RPM_PATH . "\n";
        $repoConfig .= "enabled=1\n";
        $repoConfig .= "gpgcheck=0\n";
        file_put_contents($repoConfigFile, $repoConfig);

        // Write the modified YAML content to the repository folder
        $distModuleFile = DIST_RPM_PATH . '/static-php.yaml';
        file_put_contents($distModuleFile, $yamlContent);

        // Step 1: Run createrepo_c command to create repository metadata
        echo "Creating initial metadata with createrepo_c...\n";
        $createrepoProcess = new Process(['createrepo_c', DIST_RPM_PATH]);
        $createrepoProcess->setTimeout(null);
        $createrepoProcess->run(function ($type, $buffer) {
            echo $buffer;
        });

        // Step 2: Gzip the module YAML file
        echo "Adding module metadata...\n";
        $moduleContent = file_get_contents($distModuleFile);
        $gzippedContent = gzencode($moduleContent);
        file_put_contents(DIST_RPM_PATH . '/repodata/modules.yaml.gz', $gzippedContent);

        // Step 3: Run modifyrepo_c command to add module metadata to the repository
        $modifyrepoProcess = new Process([
            'modifyrepo_c',
            '--mdtype=modules',
            DIST_RPM_PATH . '/repodata/modules.yaml.gz',
            DIST_RPM_PATH . '/repodata'
        ]);
        $modifyrepoProcess->setTimeout(null);
        $modifyrepoProcess->run(function ($type, $buffer) {
            echo $buffer;
        });

        return [
            'name' => 'static-php-repo',
            'files' => [
                $repoConfigFile => '/etc/yum.repos.d/static-php.repo',
            ],
            'directories' => [
                '/etc/yum.repos.d',
            ],
        ];
    }

    /**
     * Get the full PHP version (including patch level) from the PHP binary
     *
     * @return string Full PHP version (e.g., 8.4.7)
     */
    private function getFullPhpVersion(): string
    {
        // Extract PHP version from the binary
        $phpBinary = BUILD_BIN_PATH . '/php';
        if (!file_exists($phpBinary)) {
            echo "Warning: PHP binary not found at {$phpBinary}, using version {$this->phpVersion}.0\n";
            return $this->phpVersion . '.0';
        }

        // Get PHP version
        $versionProcess = new Process([$phpBinary, '-r', 'echo PHP_VERSION;']);
        $versionProcess->run();
        $fullPhpVersion = trim($versionProcess->getOutput());

        if (empty($fullPhpVersion)) {
            echo "Warning: Could not determine PHP version, using version {$this->phpVersion}.0\n";
            return $this->phpVersion . '.0';
        }

        echo "Detected full PHP version: {$fullPhpVersion}\n";
        return $fullPhpVersion;
    }
}
