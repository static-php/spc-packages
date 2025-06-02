<?php

namespace staticphp\step;

use Symfony\Component\Process\Process;
use staticphp\CraftConfig;

class CreatePackages
{
    private static $extensions = [];
    private static $sharedExtensions = [];
    private static $sapis = [];

    public static function run(): true
    {
        // Load the craft.yml configuration
        self::loadConfig();

        // Create packages for each SAPI (cli, fpm, embed)
        self::createSapiPackages();

        // Create packages for each extension
        self::createExtensionPackages();

        echo "Package creation completed.\n";
        return true;
    }

    private static function loadConfig(): void
    {
        $craftYmlPath = BASE_PATH . '/config/craft.yml';
        echo "Loading configuration from {$craftYmlPath}...\n";

        // Use the CraftConfig component to load the configuration
        $craftConfig = CraftConfig::getInstance();

        // Get the configuration from the CraftConfig component
        self::$extensions = $craftConfig->getStaticExtensions();
        self::$sharedExtensions = $craftConfig->getSharedExtensions();
        self::$sapis = $craftConfig->getSapis();

        echo "Loaded configuration:\n";
        echo "- SAPIs: " . implode(', ', self::$sapis) . "\n";
        echo "- Extensions: " . implode(', ', self::$extensions) . "\n";
        echo "- Shared Extensions: " . implode(', ', self::$sharedExtensions) . "\n";
    }

    private static function createSapiPackages(): void
    {
        echo "Creating packages for SAPIs...\n";

        foreach (self::$sapis as $sapi) {
            echo "Creating package for SAPI: {$sapi}...\n";

            // Determine the package class based on SAPI
            $packageClass = "\\staticphp\\package\\{$sapi}";

            if (!class_exists($packageClass)) {
                echo "Warning: Package class not found for SAPI: {$sapi}\n";
                continue;
            }

            // Create the package
            $package = new $packageClass();
            $config = $package->getFpmConfig();

            // Create packages using FPM with "php-" prefix
            self::createPackageWithFpm("php-{$sapi}", $config);
        }
    }

    private static function createExtensionPackages(): void
    {
        echo "Creating packages for extensions...\n";

        // Combine both extension lists
        $allExtensions = array_merge(self::$extensions, self::$sharedExtensions);
        $allExtensions = array_unique($allExtensions);

        foreach ($allExtensions as $extension) {
            echo "Creating package for extension: {$extension}...\n";

            // Create a package for this extension
            $package = new \staticphp\extension($extension);
            $config = $package->getFpmConfig();

            if (!file_exists(INI_PATH . '/extension/' . $extension . '.ini')) {
                echo "Warning: INI file for extension {$extension} not found, skipping package creation.\n";
                continue;
            }

            // Create packages using FPM with php- prefix
            self::createPackageWithFpm("php-{$extension}", $config);
        }
    }

    private static function createPackageWithFpm($name, $config): void
    {
        echo "Creating packages for {$name} using FPM...\n";

        // Extract PHP version and architecture
        [$phpVersion, $architecture] = self::getPhpVersionAndArchitecture();

        // Determine the next available iteration
        $iteration = self::getNextIteration($name, $phpVersion, $architecture);

        // Create RPM package
        self::createRpmPackage($name, $config, $phpVersion, $architecture, $iteration);

        // Create DEB package
        self::createDebPackage($name, $config, $phpVersion, $architecture, $iteration);
    }

    private static function createRpmPackage($name, $config, $phpVersion, $architecture, $iteration): void
    {
        echo "Creating RPM package for {$name}...\n";

        // Prepare FPM arguments
        $fpmArgs = [
            'fpm',
            '-s', 'dir',
            '-t', 'rpm',
            '-p', DIST_RPM_PATH,
            '--name', $name,
            '--version', $phpVersion,
            '--architecture', $architecture,
            '--iteration', $iteration,
            '--description', "Static PHP Package for {$name}",
            '--license', 'MIT',
            '--maintainer', 'Static PHP <info@static-php.dev>'
        ];

        // Add provides
        if (isset($config['provides']) && is_array($config['provides'])) {
            foreach ($config['provides'] as $provide) {
                $fpmArgs[] = '--provides';
                $fpmArgs[] = $provide;
            }
        }

        // Add dependencies
        if (isset($config['depends']) && is_array($config['depends'])) {
            foreach ($config['depends'] as $depend) {
                $fpmArgs[] = '--depends';
                $fpmArgs[] = $depend;
            }
        }

        if (isset($config['directories']) && is_array($config['directories'])) {
            foreach ($config['directories'] as $dir) {
                // Add the directory to the package
                $fpmArgs[] = '--directories';
                $fpmArgs[] = $dir;
            }
        }

        // Add config files
        if (isset($config['config-files']) && is_array($config['config-files'])) {
            foreach ($config['config-files'] as $configFile) {
                $fpmArgs[] = '--config-files';
                $fpmArgs[] = $configFile;
            }
        }

        // Add files to package
        if (isset($config['files']) && is_array($config['files'])) {
            foreach ($config['files'] as $source => $dest) {
                if (file_exists($source)) {
                    $fpmArgs[] = $source . '=' . $dest;
                } else {
                    echo "Warning: Source file not found: {$source}\n";
                }
            }
        }

        // Add empty directories
        if (isset($config['empty_directories']) && is_array($config['empty_directories'])) {
            $emptyDir = TEMP_DIR . '/__spp_empty';
            if (!file_exists($emptyDir)) {
                mkdir($emptyDir, recursive: true);
            }
            if (is_dir($emptyDir)) {
                exec('rm -rf ' . escapeshellarg($emptyDir . '/*'));
            }
            foreach ($config['empty_directories'] as $dir) {
                $fpmArgs[] = $emptyDir . '=' . $dir;
            }
        }

        // Build the RPM package
        $rpmProcess = new Process($fpmArgs);
        $rpmProcess->setTimeout(null);
        $rpmProcess->run(function ($type, $buffer) {
            echo $buffer;
        });

        echo "RPM package created: " . DIST_RPM_PATH . "/{$name}-{$phpVersion}-{$iteration}.{$architecture}.rpm\n";
    }

    private static function createDebPackage($name, $config, $phpVersion, $architecture, $iteration): void
    {
        echo "Creating DEB package for {$name}...\n";

        // Prepare FPM arguments
        $fpmArgs = [
            'fpm',
            '-s', 'dir',
            '-t', 'deb',
            '-p', DIST_DEB_PATH,
            '--name', $name,
            '--version', $phpVersion,
            '--architecture', $architecture,
            '--iteration', $iteration,
            '--description', "Static PHP Package for {$name}",
            '--license', 'MIT',
            '--maintainer', 'Static PHP <info@static-php.dev>'
        ];

        // Add provides
        if (isset($config['provides']) && is_array($config['provides'])) {
            foreach ($config['provides'] as $provide) {
                $fpmArgs[] = '--provides';
                $fpmArgs[] = $provide;
            }
        }

        // Add dependencies
        if (isset($config['depends']) && is_array($config['depends'])) {
            foreach ($config['depends'] as $depend) {
                $fpmArgs[] = '--depends';
                $fpmArgs[] = $depend;
            }
        }

        if (isset($config['directories']) && is_array($config['directories'])) {
            foreach ($config['directories'] as $dir) {
                // Add the directory to the package
                $fpmArgs[] = '--directories';
                $fpmArgs[] = $dir;
            }
        }

        // Add config files
        if (isset($config['config-files']) && is_array($config['config-files'])) {
            foreach ($config['config-files'] as $configFile) {
                $fpmArgs[] = '--config-files';
                $fpmArgs[] = $configFile;
            }
        }
        $fpmArgs[] = '--deb-no-default-config-files'; // disable useless warning

        // Add files to package
        if (isset($config['files']) && is_array($config['files'])) {
            foreach ($config['files'] as $source => $dest) {
                if (file_exists($source)) {
                    $fpmArgs[] = $source . '=' . $dest;
                } else {
                    echo "Warning: Source file not found: {$source}\n";
                }
            }
        }

        // Add empty directories
        if (isset($config['empty_directories']) && is_array($config['empty_directories'])) {
            $emptyDir = TEMP_DIR . '/__spp_empty';
            if (!file_exists($emptyDir)) {
                mkdir($emptyDir, recursive: true);
            }
            if (is_dir($emptyDir)) {
                exec('rm -rf ' . escapeshellarg($emptyDir . '/*'));
            }
            foreach ($config['empty_directories'] as $dir) {
                $fpmArgs[] = $emptyDir . '=' . $dir;
            }
        }

        // Build the DEB package
        $debProcess = new Process($fpmArgs);
        $debProcess->setTimeout(null);
        $debProcess->run(function ($type, $buffer) {
            echo $buffer;
        });

        echo "DEB package created: " . DIST_DEB_PATH . "/{$name}_{$phpVersion}-{$iteration}_{$architecture}.deb\n";
    }

    private static function getPhpVersionAndArchitecture(): array
    {
        // Extract PHP version and architecture from the binary
        $phpBinary = BUILD_BIN_PATH . '/php';
        if (!file_exists($phpBinary)) {
            echo "Warning: PHP binary not found at {$phpBinary}\n";
            return ['1.0.0', 'x86_64']; // Fallback values
        }

        // Get PHP version
        $versionProcess = new Process([$phpBinary, '-r', 'echo PHP_VERSION;']);
        $versionProcess->run();
        $phpVersion = trim($versionProcess->getOutput());

        if (empty($phpVersion)) {
            echo "Warning: Could not determine PHP version\n";
            $phpVersion = '1.0.0'; // Fallback version
        }

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

        echo "Detected PHP version: {$phpVersion}\n";
        echo "Detected architecture: {$architecture}\n";

        return [$phpVersion, $architecture];
    }

    /**
     * Determine the next available iteration for a package
     *
     * @param string $name Package name
     * @param string $phpVersion PHP version
     * @param string $architecture Package architecture
     * @return int Next available iteration
     */
    private static function getNextIteration($name, $phpVersion, $architecture): int
    {
        $maxIteration = 0;

        // Check RPM packages
        $rpmPattern = DIST_RPM_PATH . "/{$name}-{$phpVersion}-*.{$architecture}.rpm";
        $rpmFiles = glob($rpmPattern);

        foreach ($rpmFiles as $file) {
            if (preg_match("/{$name}-{$phpVersion}-(\d+)\.{$architecture}\.rpm$/", $file, $matches)) {
                $iteration = (int)$matches[1];
                $maxIteration = max($maxIteration, $iteration);
            }
        }

        // Check DEB packages
        $debPattern = DIST_DEB_PATH . "/{$name}_{$phpVersion}-*_{$architecture}.deb";
        $debFiles = glob($debPattern);

        foreach ($debFiles as $file) {
            if (preg_match("/{$name}_{$phpVersion}-(\d+)_{$architecture}\.deb$/", $file, $matches)) {
                $iteration = (int)$matches[1];
                $maxIteration = max($maxIteration, $iteration);
            }
        }

        // Return the next iteration
        return $maxIteration + 1;
    }

}
