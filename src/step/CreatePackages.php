<?php

namespace staticphp\step;

use staticphp\extension;
use staticphp\package\rpmrepo;
use Symfony\Component\Process\Process;
use staticphp\CraftConfig;

class CreatePackages
{
    private static $extensions = [];
    private static $sharedExtensions = [];
    private static $sapis = [];
    private static $binaryDependencies = [];
    private static $packageTypes = ['rpm', 'deb'];

    /**
     * Create a repository package
     *
     * @param string $packageType Package type (rpm, deb)
     * @param string $phpVersion PHP version to use
     * @return bool True on success
     */
    public static function createRepo(string $packageType = 'rpm', string $phpVersion = '8.4'): bool
    {
        echo "Creating repository package...\n";
        echo "Using PHP version: {$phpVersion}\n";

        // Get binary dependencies once at the start
        $phpBinary = BUILD_BIN_PATH . '/php';
        self::$binaryDependencies = self::getBinaryDependencies($phpBinary);

        // Set package types
        self::$packageTypes = [$packageType];

        // Create the repository package based on package type
        if ($packageType === 'rpm') {
            $repoPackage = new rpmrepo($phpVersion);
        }
        $config = $repoPackage->getFpmConfig();

        // For the repository package, we use a fixed version and iteration
        $packageName = 'static-php';
        $version = '1';
        $iteration = self::getNextIteration($packageName, $version, 'noarch');
        $architecture = "noarch";

        // Create the package
        self::createRpmPackage($packageName, $config, $version, $architecture, $iteration, hasDependencies: false);

        echo "Repository package creation completed.\n";
        return true;
    }

    public static function run($packageNames = null, string $packageTypes = 'rpm,deb'): true
    {
        // Load the craft.yml configuration
        self::loadConfig();

        // Get binary dependencies once at the start
        $phpBinary = BUILD_BIN_PATH . '/php';
        self::$binaryDependencies = self::getBinaryDependencies($phpBinary);

        // Parse package types
        self::$packageTypes = explode(',', strtolower($packageTypes));

        if ($packageNames !== null) {
            // Convert single string to array for backward compatibility
            if (is_string($packageNames)) {
                $packageNames = [$packageNames];
            }

            foreach ($packageNames as $packageName) {
                echo "Building package: {$packageName}\n";

                // Check if it's a SAPI package
                if (in_array($packageName, self::$sapis)) {
                    self::createSapiPackage($packageName);
                }
                // Check if it's an extension package
                elseif (in_array($packageName, self::$sharedExtensions)) {
                    self::createExtensionPackage($packageName);
                }
                else {
                    echo "Warning: Package {$packageName} not found in configuration.\n";
                }
            }
        } else {
            // Create packages for each SAPI (cli, fpm, embed)
            self::createSapiPackages();

            // Create packages for each extension
            self::createExtensionPackages();
        }

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
            self::createSapiPackage($sapi);
        }
    }

    private static function createSapiPackage(string $sapi): void
    {
        echo "Creating package for SAPI: {$sapi}...\n";

        // Determine the package class based on SAPI
        $packageClass = "\\staticphp\\package\\{$sapi}";

        if (!class_exists($packageClass)) {
            echo "Warning: Package class not found for SAPI: {$sapi}\n";
            return;
        }

        // Extract PHP version and architecture
        [$phpVersion, $architecture] = self::getPhpVersionAndArchitecture();

        // Determine the next available iteration
        $iteration = self::getNextIteration(self::getPrefix() . "-{$sapi}", $phpVersion, $architecture);

        // Create the package
        $package = new $packageClass();
        $config = $package->getFpmConfig($phpVersion, $iteration);

        self::createPackageWithFpm(self::getPrefix() . "-{$sapi}", $config, $phpVersion, $architecture, $iteration);
    }

    private static function createExtensionPackages(): void
    {
        echo "Creating packages for extensions...\n";

        // Only create packages for shared extensions, not static ones
        foreach (self::$sharedExtensions as $extension) {
            self::createExtensionPackage($extension);
        }
    }

    private static function createExtensionPackage(string $extension): void
    {
        echo "Creating package for extension: {$extension}...\n";

        // Extract PHP version and architecture
        [$phpVersion, $architecture] = self::getPhpVersionAndArchitecture();

        // Determine the next available iteration
        $iteration = self::getNextIteration(self::getPrefix() . "-{$extension}", $phpVersion, $architecture);

        // Create a package for this extension
        $package = new extension($extension);
        $config = $package->getFpmConfig();

        if (!file_exists(INI_PATH . '/extension/' . $extension . '.ini')) {
            echo "Warning: INI file for extension {$extension} not found, skipping package creation.\n";
            return;
        }

        self::createPackageWithFpm(self::getPrefix() . "-{$extension}", $config, $phpVersion, $architecture, $iteration);
    }

    private static function createPackageWithFpm(string $name, array $config, string $phpVersion, string $architecture, string $iteration): void
    {
        if (in_array('rpm', self::$packageTypes)) {
            self::createRpmPackage($name, $config, $phpVersion, $architecture, $iteration);
        }

        if (in_array('deb', self::$packageTypes)) {
            self::createDebPackage($name, $config, $phpVersion, $architecture, $iteration);
        }
    }

    private static function createRpmPackage(string $name, array $config, string $phpVersion, string $architecture, string $iteration, bool $hasDependencies = true): void
    {
        echo "Creating RPM package for {$name}...\n";

        $fpmArgs = [
            'fpm',
            '-s', 'dir',
            '-t', 'rpm',
            '-p', DIST_RPM_PATH,
            '--name', $name,
            '--version', $phpVersion,
            '--iteration', $iteration,
            '--architecture', $architecture,
            '--description', "Static PHP Package for {$name}",
            '--license', 'MIT',
            '--maintainer', 'Static PHP <info@static-php.dev>'
        ];

        if (isset($config['provides']) && is_array($config['provides'])) {
            foreach ($config['provides'] as $provide) {
                $fpmArgs[] = '--provides';
                $fpmArgs[] = "$provide = $phpVersion-$iteration";
            }
        }

        // Add obsoletes
        if (isset($config['replaces']) && is_array($config['replaces'])) {
            foreach ($config['replaces'] as $replace) {
                $fpmArgs[] = '--replaces';
                $fpmArgs[] = "$replace < {$phpVersion}-{$iteration}";
            }
        }

        if ($hasDependencies) {
            foreach (self::$binaryDependencies as $lib => $version) {
                $fpmArgs[] = '--depends';
                $fpmArgs[] = "{$lib}({$version})(64bit)";
            }
            if (isset($config['depends']) && is_array($config['depends'])) {
                foreach ($config['depends'] as $depend) {
                    $fpmArgs[] = '--depends';
                    $fpmArgs[] = $depend;
                }
            }
        }
        else {
            $fpmArgs[] = '--no-depends';
        }

        if (isset($config['directories']) && is_array($config['directories'])) {
            foreach ($config['directories'] as $dir) {
                $fpmArgs[] = '--directories';
                $fpmArgs[] = $dir;
            }
        }

        if (isset($config['config-files']) && is_array($config['config-files'])) {
            foreach ($config['config-files'] as $configFile) {
                $fpmArgs[] = '--config-files';
                $fpmArgs[] = $configFile;
            }
        }

        if (isset($config['files']) && is_array($config['files'])) {
            foreach ($config['files'] as $source => $dest) {
                if (file_exists($source)) {
                    $fpmArgs[] = $source . '=' . $dest;
                } else {
                    echo "Warning: Source file not found: {$source}\n";
                }
            }
        }

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

    private static function createDebPackage(string $name, array $config, string $phpVersion, string $architecture, string $iteration, bool $hasDependencies = true): void
    {
        echo "Creating DEB package for {$name}...\n";

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

        if (isset($config['provides']) && is_array($config['provides'])) {
            foreach ($config['provides'] as $provide) {
                $fpmArgs[] = '--provides';
                $fpmArgs[] = "$provide (= $phpVersion)";
            }
        }

        // Add obsoletes
        if (isset($config['replaces']) && is_array($config['replaces'])) {
            foreach ($config['replaces'] as $replace) {
                $fpmArgs[] = '--replaces';
                $fpmArgs[] = "$replace < {$phpVersion}-{$iteration})";
            }
        }

        if ($hasDependencies) {
            foreach (self::$binaryDependencies as $lib => $version) {
                $lib = str_replace('.so.', '', $lib); // remove .so. for deb compatibility
                $lib = preg_replace('/_\D+/', '', $lib);
                $numericVersion = preg_replace('/[^0-9.]/', '',  $version);
                $fpmArgs[] = '--depends';
                $fpmArgs[] = "$lib (>= {$numericVersion})";
            }
            if (isset($config['depends']) && is_array($config['depends'])) {
                foreach ($config['depends'] as $depend) {
                    $fpmArgs[] = '--depends';
                    $fpmArgs[] = $depend;
                }
            }
        }
        else {
            $fpmArgs[] = '--no-depends';
        }

        if (isset($config['directories']) && is_array($config['directories'])) {
            foreach ($config['directories'] as $dir) {
                $fpmArgs[] = '--directories';
                $fpmArgs[] = $dir;
            }
        }

        if (isset($config['config-files']) && is_array($config['config-files'])) {
            foreach ($config['config-files'] as $configFile) {
                $fpmArgs[] = '--config-files';
                $fpmArgs[] = $configFile;
            }
        }
        $fpmArgs[] = '--deb-no-default-config-files'; // disable useless warning

        if (isset($config['files']) && is_array($config['files'])) {
            foreach ($config['files'] as $source => $dest) {
                if (file_exists($source)) {
                    $fpmArgs[] = $source . '=' . $dest;
                } else {
                    echo "Warning: Source file not found: {$source}\n";
                }
            }
        }

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
            throw new \Exception("Warning: PHP binary not found at {$phpBinary}\n");
        }

        // Get PHP version
        $versionProcess = new Process([$phpBinary, '-r', 'echo PHP_VERSION;']);
        $versionProcess->run();
        $phpVersion = trim($versionProcess->getOutput());

        if (empty($phpVersion)) {
            throw new \Exception("Warning: Could not determine PHP version\n");
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
     * Get dependencies
     *
     * @param string $phpBinary Path to the PHP binary
     * @return array Array containing GLIBC and CXXABI versions
     */
    private static function getBinaryDependencies(string $binaryPath): array
    {
        $process = new Process(['ldd', '-v', $binaryPath]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("ldd failed: " . $process->getErrorOutput());
        }

        $output = $process->getOutput();

        // Discard everything before "$binaryPath:"
        $output = preg_replace('/.*?' . preg_quote($binaryPath, '/') . ':\s*\n/s', '', $output, 1);

        // Discard everything after the next path-based section header like "/lib64/libstdc++.so.6:"
        $output = preg_replace('/\n\s*\/.*?:.*/s', '', $output, 1);

        $lines = explode("\n", $output);
        $dependencies = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (preg_match('#^([\w.\-+]+)\s+\(([^)]+)\)\s+=>\s+(/\S+)$#', $trimmed, $m)) {
                $lib = $m[1];
                $version = $m[2];

                // Ignore non-numeric versions like GLIBC_PRIVATE
                if (!preg_match('/\d+(\.\d+)+/', $version)) {
                    continue;
                }

                // Store highest version only
                if (!isset($dependencies[$lib]) || version_compare($version, $dependencies[$lib], '>')) {
                    $dependencies[$lib] = $version;
                }
            }
        }

        return $dependencies;
    }

    /**
     * Determine the next available iteration for a package
     *
     * @param string $name Package name
     * @param string $phpVersion PHP version
     * @param string $architecture Package architecture
     * @return int Next available iteration
     */
    private static function getNextIteration(string $name, string $phpVersion, string $architecture): int
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

    public static function getPrefix(): string
    {
        return 'php-zts';
    }
}
