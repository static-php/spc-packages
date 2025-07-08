<?php

namespace staticphp\package;

use staticphp\package;
use staticphp\step\CreatePackages;
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
        $yamlContent = str_replace('YYYYMMDD', (new \DateTime())->format('Ymd'), $yamlContent);
        $yamlContent = str_replace('majorminorpatch', $fullPhpVersion, $yamlContent);
        $yamlContent = str_replace('majorminor', $this->phpVersion, $yamlContent);
        $yamlContent = str_replace('iteration', '1', $yamlContent);
        $yamlContent = str_replace('prefix', CreatePackages::getPrefix(), $yamlContent);

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

        // Append all available artifacts from dist/rpm with a matching version
        if (isset($moduleData['data']['artifacts']['rpms']) && is_array($moduleData['data']['artifacts']['rpms'])) {
            $rpmPattern = DIST_RPM_PATH . '/' . CreatePackages::getPrefix() . '*' . $fullPhpVersion . '*.rpm';
            $rpmFiles = glob($rpmPattern);

            $phpVersion = str_replace('.', '', SPP_PHP_VERSION);
            $frankenFiles = glob(DIST_RPM_PATH . "/frankenphp-*{$phpVersion}*.rpm");

            echo "Scanning for RPM artifacts in: " . DIST_RPM_PATH . "\n";
            echo "Found " . count($rpmFiles) . " RPM files\n";

            foreach ([...$rpmFiles, ...$frankenFiles] as $rpmFile) {
                $filename = basename($rpmFile);

                // Convert filename to artifact format (remove .rpm extension and path)
                $artifact = str_replace('.rpm', '', $filename);
                $artifact = str_replace('-' . $fullPhpVersion . '-', '-0:' . $fullPhpVersion . '-', $artifact);
                $artifact = str_replace("frankenphp-", "frankenphp-0:", $artifact);

                // Add to the artifact list if not already present
                if (!in_array($artifact, $moduleData['data']['artifacts']['rpms'])) {
                    $moduleData['data']['artifacts']['rpms'][] = $artifact;
                }
            }

            // Update the YAML content with the modified artifacts list
            $yamlContent = Yaml::dump($moduleData, 10, 2);
        } else {
            echo "Warning: artifacts.rpms section not found in YAML or is not an array\n";
        }

        // Extract module information
        $moduleName = $moduleData['data']['name'] ?? 'static-php';
        $moduleStream = $moduleData['data']['stream'] ?? $this->phpVersion;

        // Create the repository directory structure
        $moduleDir = $repoDir . '/modules/' . $moduleName;
        if (!file_exists($moduleDir)) {
            mkdir($moduleDir, 0755, true);
        }

        // Create a simple repository configuration file
        $repoConfigFile = $repoDir . '/static-php.repo';
        $repoConfig  = "[static-php]\n";
        $repoConfig .= "name=Static PHP repository\n";
        $repoConfig .= "baseurl=https://rpm.henderkes.com/\$basearch/el\$releasever\n";
        $repoConfig .= "enabled=1\n";
        $repoConfig .= "gpgcheck=0\n";

        file_put_contents($repoConfigFile, $repoConfig);

        file_put_contents($repoConfigFile, $repoConfig);

        // Ensure the dist/rpm directory exists
        if (!file_exists(DIST_RPM_PATH)) {
            echo "Creating directory: " . DIST_RPM_PATH . "\n";
            mkdir(DIST_RPM_PATH, 0755, true);
        }

        // Create static-php-{majorminor}.yaml instead of static-php.yaml
        $distModuleFile = DIST_RPM_PATH . '/static-php-' . $this->phpVersion . '.yaml';
        $existingModules = [];

        if (file_exists($distModuleFile)) {
            echo "Existing static-php-{$this->phpVersion}.yaml found, updating...\n";
            $existingYaml = file_get_contents($distModuleFile);

            try {
                // Parse the existing YAML content
                $existingModules = Yaml::parse($existingYaml);

                // If the file is empty or contains invalid YAML, initialize as empty array
                if ($existingModules === null) {
                    echo "Warning: Existing static-php-{$this->phpVersion}.yaml is empty or invalid, initializing as new file\n";
                    $existingModules = [];
                }
                // If it's not a multi-document YAML, convert it to an array
                else if (!is_array($existingModules) || !isset($existingModules[0])) {
                    $existingModules = [$existingModules];
                }
            } catch (\Exception $e) {
                echo "Warning: Error parsing existing static-php-{$this->phpVersion}.yaml: " . $e->getMessage() . "\n";
                echo "Initializing as new file\n";
                $existingModules = [];
            }
        }

        // Parse the new module data
        $newModule = Yaml::parse($yamlContent);

        // Check if a module with the same name and stream already exists
        $updated = false;
        if (!empty($existingModules)) {
            foreach ($existingModules as $key => $module) {
                if (isset($module['data']['name']) && isset($module['data']['stream']) &&
                    $module['data']['name'] === $moduleData['data']['name'] &&
                    $module['data']['stream'] === $moduleData['data']['stream']) {
                    // Update the existing module
                    $existingModules[$key] = $newModule;
                    $updated = true;
                    echo "Updated existing module: {$moduleName}:{$moduleStream}\n";
                    break;
                }
            }
        }

        // If no matching module was found, append the new one
        if (!$updated) {
            $existingModules[] = $newModule;
            echo "Added new module: {$moduleName}:{$moduleStream}\n";
        }

        // Convert the modules array back to YAML and write to file
        $combinedYaml = '';
        foreach ($existingModules as $index => $module) {
            $moduleYaml = Yaml::dump($module, 10, 2);
            // Add the document separator for all documents
            // This is required for multi-document YAML files
            $combinedYaml .= "---\n" . $moduleYaml;
        }

        file_put_contents($distModuleFile, $combinedYaml);

        // Combine all static-php-*.yaml files in ascending order
        echo "Combining all static-php-*.yaml files...\n";
        $combinedFile = DIST_RPM_PATH . '/static-php.yaml';
        $yamlFiles = glob(DIST_RPM_PATH . '/static-php-*.yaml');
        sort($yamlFiles); // Sort files in ascending order

        $allYamlContent = '';
        foreach ($yamlFiles as $yamlFile) {
            $fileContent = file_get_contents($yamlFile);
            if (!empty($fileContent)) {
                // Process each document in the file
                $documents = preg_split('/^---\s*$/m', $fileContent);
                foreach ($documents as $document) {
                    $document = trim($document);
                    if (empty($document)) continue;

                    // Remove any existing document end markers
                    $document = preg_replace('/\.\.\.\s*$/m', '', $document);

                    // Add document start and end markers
                    $allYamlContent .= "---\n" . $document . "\n...\n";
                }
            }
        }

        // Write the combined content to static-php.yaml
        file_put_contents($combinedFile, $allYamlContent);
        echo "Combined YAML file created at: $combinedFile\n";

        // Step 1: Run createrepo_c command to create repository metadata
        echo "Creating initial metadata with createrepo_c...\n";
        $createrepoProcess = new Process(['createrepo_c', DIST_RPM_PATH]);
        $createrepoProcess->setTimeout(null);
        $createrepoProcess->run(function ($type, $buffer) {
            echo $buffer;
        });

        // Step 2: Gzip the combined module YAML file
        echo "Adding module metadata...\n";
        $moduleContent = file_get_contents($combinedFile);
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
            'name' => 'static-php',
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

    public function getFpmExtraArgs(): array
    {
        return [];
    }
}
