<?php

namespace staticphp\step;

use staticphp\extension;
use staticphp\package\composer;
use Symfony\Component\Process\Process;
use staticphp\CraftConfig;

class CreatePackages
{
    private static $extensions = [];
    private static $sharedExtensions = [];
    private static $sapis = [];
    private static $binaryDependencies = [];
    private static $packageTypes = [];


    public static function run($packageNames = null, string $packageTypes = 'rpm,deb', string $phpVersion = '8.4'): true
    {
        define('SPP_PACKAGE_PHP_VERSION', $phpVersion);

        self::loadConfig();

        $phpBinary = BUILD_BIN_PATH . '/php';
        self::$binaryDependencies = self::getBinaryDependencies($phpBinary);

        self::$packageTypes = explode(',', strtolower($packageTypes));

        if ($packageNames !== null) {
            if (is_string($packageNames)) {
                $packageNames = [$packageNames];
            }

            foreach ($packageNames as $packageName) {
                echo "Building package: {$packageName}\n";

                if (in_array($packageName, self::$sapis) && $packageName !== 'frankenphp') {
                    self::createSapiPackage($packageName);
                }
                elseif ($packageName === 'frankenphp') {
                    self::createFrankenPhpPackage();
                }
                elseif ($packageName === 'composer') {
                    self::createComposerPackage();
                }
                elseif ($packageName === 'devel') {
                    self::createSapiPackage($packageName);
                }
                elseif (in_array($packageName, self::$sharedExtensions)) {
                    self::createExtensionPackage($packageName);
                }
                else {
                    echo "Warning: Package {$packageName} not found in configuration.\n";
                }
            }
        }
        else {
            self::createSapiPackages();
            self::createFrankenPhpPackage();
            self::createComposerPackage();
            self::createExtensionPackages();
        }

        echo "Package creation completed.\n";
        return true;
    }

    private static function loadConfig(): void
    {
        echo "Loading configuration from Twig template...\n";

        $craftConfig = CraftConfig::getInstance();

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

        $packageClass = "\\staticphp\\package\\{$sapi}";

        if (!class_exists($packageClass)) {
            echo "Warning: Package class not found for SAPI: {$sapi}\n";
            return;
        }

        [$phpVersion, $architecture] = self::getPhpVersionAndArchitecture();

        $iteration = self::getNextIteration(self::getPrefix() . "-{$sapi}", $phpVersion, $architecture);

        $package = new $packageClass();
        $config = $package->getFpmConfig($phpVersion, $iteration);

        self::createPackageWithFpm(self::getPrefix() . "-{$sapi}", $config, $phpVersion, $architecture, $iteration);
    }

    private static function createExtensionPackages(): void
    {
        echo "Creating packages for extensions...\n";

        foreach (self::$sharedExtensions as $extension) {
            self::createExtensionPackage($extension);
        }
    }

    private static function createExtensionPackage(string $extension): void
    {
        echo "Creating package for extension: {$extension}...\n";

        [$phpVersion, $architecture] = self::getPhpVersionAndArchitecture();

        $iteration = self::getNextIteration(self::getPrefix() . "-{$extension}", $phpVersion, $architecture);

        $package = new extension($extension);
        $packageClass = "\\staticphp\\package\\{$extension}";
        if (class_exists($packageClass)) {
            $package = new $packageClass($extension);
        }
        $config = $package->getFpmConfig();

        if (!file_exists(INI_PATH . '/extension/' . $extension . '.ini')) {
            echo "Warning: INI file for extension {$extension} not found, skipping package creation.\n";
            return;
        }

        self::createPackageWithFpm(self::getPrefix() . "-{$extension}", $config, $phpVersion, $architecture, $iteration, $package->getFpmExtraArgs());;
    }

    private static function createPackageWithFpm(string $name, array $config, string $phpVersion, string $architecture, string $iteration, array $extraArgs = []): void
    {
        if (in_array('rpm', self::$packageTypes)) {
            self::createRpmPackage($name, $config, $phpVersion, $architecture, $iteration, $extraArgs);
        }

        if (in_array('deb', self::$packageTypes)) {
            self::createDebPackage($name, $config, $phpVersion, $architecture, $iteration, $extraArgs);
        }
    }

    private static function createRpmPackage(string $name, array $config, string $phpVersion, string $architecture, string $iteration, array $extraArgs = []): void
    {
        echo "Creating RPM package for {$name}...\n";

        $fpmArgs = [...[
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
        ], ...$extraArgs];

        if (isset($config['provides']) && is_array($config['provides'])) {
            foreach ($config['provides'] as $provide) {
                $fpmArgs[] = '--provides';
                $fpmArgs[] = "$provide = $phpVersion-$iteration";
                if (str_ends_with($provide, '.so')) {
                    $provide = str_replace('.so', '.so()(64bit)', $provide);
                    $fpmArgs[] = '--provides';
                    $fpmArgs[] = "$provide = $phpVersion-$iteration";
                }
            }
        }

        if (isset($config['replaces']) && is_array($config['replaces'])) {
            foreach ($config['replaces'] as $replace) {
                $fpmArgs[] = '--replaces';
                $fpmArgs[] = "$replace < {$phpVersion}-{$iteration}";
            }
        }

        foreach (self::$binaryDependencies as $lib => $version) {
            $fpmArgs[] = '--depends';
            $fpmArgs[] = "{$lib}({$version})(64bit)";
        }
        if (isset($config['depends']) && is_array($config['depends'])) {
            foreach ($config['depends'] as $depend) {
                $fpmArgs[] = '--depends';
                if (preg_match('/\.so(\.\d+)*$/', $depend)) {
                    $depend .= '()(64bit)';
                }
                $fpmArgs[] = $depend;
            }
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
                }
                else {
                    echo "Warning: Source file not found: {$source}\n";
                }
            }
        }

        if (isset($config['empty_directories']) && is_array($config['empty_directories'])) {
            $emptyDir = TEMP_DIR . '/spp_empty';
            if (!file_exists($emptyDir) && !mkdir($emptyDir, 0755, true) && !is_dir($emptyDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $emptyDir));
            }
            if (is_dir($emptyDir)) {
                $files = array_diff(scandir($emptyDir), ['.', '..']);
                if (!empty($files)) {
                    exec('rm -rf ' . escapeshellarg($emptyDir . '/*'));
                }
            }
            foreach ($config['empty_directories'] as $dir) {
                $fpmArgs[] = $emptyDir . '=' . $dir;
            }
        }

        $rpmProcess = new Process($fpmArgs);
        $rpmProcess->setTimeout(null);
        $rpmProcess->run(function ($type, $buffer) {
            echo $buffer;
        });

        echo "RPM package created: " . DIST_RPM_PATH . "/{$name}-{$phpVersion}-{$iteration}.{$architecture}.rpm\n";
    }

    private static function createDebPackage(string $name, array $config, string $phpVersion, string $architecture, string $iteration, array $extraArgs = []): void
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

        if (isset($config['replaces']) && is_array($config['replaces'])) {
            foreach ($config['replaces'] as $replace) {
                $fpmArgs[] = '--replaces';
                $fpmArgs[] = "$replace < {$phpVersion}-{$iteration})";
            }
        }

        foreach (self::$binaryDependencies as $lib => $version) {
            $lib = str_replace('.so.', '', $lib);
            $lib = preg_replace('/_\D+/', '', $lib);
            $numericVersion = preg_replace('/[^0-9.]/', '', $version);
            $fpmArgs[] = '--depends';
            $fpmArgs[] = "$lib (>= {$numericVersion})";
        }
        if (isset($config['depends']) && is_array($config['depends'])) {
            foreach ($config['depends'] as $depend) {
                $fpmArgs[] = '--depends';
                $fpmArgs[] = $depend;
            }
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
        $fpmArgs[] = '--deb-no-default-config-files';

        if (isset($config['files']) && is_array($config['files'])) {
            foreach ($config['files'] as $source => $dest) {
                if (file_exists($source)) {
                    $fpmArgs[] = $source . '=' . $dest;
                }
                else {
                    echo "Warning: Source file not found: {$source}\n";
                }
            }
        }

        if (isset($config['empty_directories']) && is_array($config['empty_directories'])) {
            $emptyDir = TEMP_DIR . '/spp_empty';
            if (!file_exists($emptyDir) && !mkdir($emptyDir, true) && !is_dir($emptyDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $emptyDir));
            }
            if (is_dir($emptyDir)) {
                $files = array_diff((array) scandir($emptyDir), ['.', '..']);
                if (!empty($files)) {
                    exec('rm -rf ' . escapeshellarg($emptyDir . '/*'));
                }
            }
            foreach ($config['empty_directories'] as $dir) {
                $fpmArgs[] = $emptyDir . '=' . $dir;
            }
        }

        $debProcess = new Process($fpmArgs);
        $debProcess->setTimeout(null);
        $debProcess->run(function ($type, $buffer) {
            echo $buffer;
        });

        echo "DEB package created: " . DIST_DEB_PATH . "/{$name}_{$phpVersion}-{$iteration}_{$architecture}.deb\n";
    }

    private static function getPhpVersionAndArchitecture(): array
    {
        $phpVersion = defined('SPP_PACKAGE_PHP_VERSION') ? SPP_PACKAGE_PHP_VERSION : SPP_PHP_VERSION;

        $phpBinary = BUILD_BIN_PATH . '/php';
        if (!file_exists($phpBinary)) {
            echo "Warning: PHP binary not found at {$phpBinary}, using fallback method for architecture detection\n";
        }

        $archProcess = new Process(['uname', '-m']);
        $archProcess->run();
        $architecture = trim($archProcess->getOutput());

        if (empty($architecture)) {
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

    private static function getBinaryDependencies(string $binaryPath): array
    {
        $process = new Process(['ldd', '-v', $binaryPath]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("ldd failed: " . $process->getErrorOutput());
        }

        $output = $process->getOutput();

        $output = preg_replace('/.*?' . preg_quote($binaryPath, '/') . ':\s*\n/s', '', $output, 1);

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

                if (!preg_match('/\d+(\.\d+)+/', $version)) {
                    continue;
                }

                if (!isset($dependencies[$lib]) || version_compare($version, $dependencies[$lib], '>')) {
                    $dependencies[$lib] = $version;
                }
            }
        }

        return $dependencies;
    }

    private static function getNextIteration(string $name, string $phpVersion, string $architecture): int
    {
        $maxIteration = 0;

        $rpmPattern = DIST_RPM_PATH . "/{$name}-{$phpVersion}-*.{$architecture}.rpm";
        $rpmFiles = glob($rpmPattern);

        foreach ($rpmFiles as $file) {
            if (preg_match("/{$name}-{$phpVersion}-(\d+)\.{$architecture}\.rpm$/", $file, $matches)) {
                $iteration = (int)$matches[1];
                $maxIteration = max($maxIteration, $iteration);
            }
        }

        $debPattern = DIST_DEB_PATH . "/{$name}_{$phpVersion}-*_{$architecture}.deb";
        $debFiles = glob($debPattern);

        foreach ($debFiles as $file) {
            if (preg_match("/{$name}_{$phpVersion}-(\d+)_{$architecture}\.deb$/", $file, $matches)) {
                $iteration = (int)$matches[1];
                $maxIteration = max($maxIteration, $iteration);
            }
        }

        return $maxIteration + 1;
    }

    public static function getPrefix(): string
    {
        return 'php-zts';
    }

    private static function createFrankenPhpPackage()
    {
        echo "Creating FrankenPHP package\n";

        [, $architecture] = self::getPhpVersionAndArchitecture();

        self::prepareFrankenPhpRepository();

        if (in_array('rpm', self::$packageTypes)) {
            self::createRpmFrankenPhpPackage($architecture);
        }
        if (in_array('deb', self::$packageTypes)) {
            self::createDebFrankenPhpPackage($architecture);
        }
    }

    private static function createRpmFrankenPhpPackage(mixed $architecture)
    {
        echo "Creating RPM package for FrankenPHP...\n";

        $packageFolder = DIST_PATH . '/frankenphp/package';
        $phpVersion = str_replace('.', '', SPP_PHP_VERSION);
        $phpEmbedName = 'lib' . self::getPrefix() . '-' . $phpVersion . '.so';

        $ldLibraryPath = 'LD_LIBRARY_PATH=' . BUILD_LIB_PATH;
        [, $output] = shell()->execWithResult($ldLibraryPath . ' ' . BUILD_BIN_PATH . '/frankenphp --version');
        $output = implode("\n", $output);
        preg_match('/FrankenPHP v(\d+\.\d+\.\d+)/', $output, $matches);
        $latestTag = $matches[1];
        $version = $latestTag . '_' . $phpVersion;

        $name = "frankenphp";

        $iteration = self::getNextIteration($name, $version, $architecture);

        $fpmArgs = [
            'fpm',
            '-s', 'dir',
            '-t', 'rpm',
            '-p', DIST_RPM_PATH,
            '-n', $name,
            '-v', $version,
            '--config-files', '/etc/frankenphp/Caddyfile',
        ];

        foreach (self::$binaryDependencies as $lib => $dependencyVersion) {
            $fpmArgs[] = '--depends';
            $fpmArgs[] = "$lib({$dependencyVersion})(64bit)";
        }

        if (!is_dir("{$packageFolder}/empty/") && !mkdir("{$packageFolder}/empty/", true) && !is_dir("{$packageFolder}/empty/")) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', "{$packageFolder}/empty/"));
        }

        $fpmArgs = [...$fpmArgs, ...[
            '--depends', "$phpEmbedName",
            '--before-install', "{$packageFolder}/rhel/preinstall.sh",
            '--after-install', "{$packageFolder}/rhel/postinstall.sh",
            '--before-remove', "{$packageFolder}/rhel/preuninstall.sh",
            '--after-remove', "{$packageFolder}/rhel/postuninstall.sh",
            '--iteration', $iteration,
            '--rpm-user', 'frankenphp',
            '--rpm-group', 'frankenphp',
            BUILD_BIN_PATH . '/frankenphp=/usr/bin/frankenphp',
            "{$packageFolder}/rhel/frankenphp.service=/usr/lib/systemd/system/frankenphp.service",
            "{$packageFolder}/Caddyfile=/etc/frankenphp/Caddyfile",
            "{$packageFolder}/content/=/usr/share/frankenphp",
            "{$packageFolder}/empty/=/var/lib/frankenphp"
        ]];

        $rpmProcess = new Process($fpmArgs);
        $rpmProcess->setTimeout(null);
        $rpmProcess->run(function ($type, $buffer) {
            echo $buffer;
        });

        echo "RPM package created: " . DIST_RPM_PATH . "/{$name}-{$version}-{$iteration}.{$architecture}.rpm\n";
    }

    private static function createDebFrankenPhpPackage(mixed $architecture)
    {

    }

    private static function createComposerPackage(): void
    {
        echo "Creating package for Composer...\n";

        $package = new composer();
        $config = $package->getFpmConfig();

        $process = new Process(['curl', '-s', 'https://api.github.com/repos/composer/composer/releases/latest']);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException("Failed to get latest Composer version: " . $process->getErrorOutput());
        }

        $releaseInfo = json_decode($process->getOutput(), true);
        $version = $releaseInfo['tag_name'];
        $version = ltrim($version, 'v');

        $iteration = self::getNextIteration('composer', $version, 'noarch');
        echo "Using iteration: {$iteration} for Composer package\n";

        self::createPackageWithFpm('composer', $config, $version, 'noarch', $iteration, $package->getFpmExtraArgs());

        echo "Composer package created successfully.\n";
    }

    private static function prepareFrankenPhpRepository(): string
    {
        $repoUrl = 'https://github.com/php/frankenphp.git';
        $targetPath = DIST_PATH . '/frankenphp';

        $tagProcess = new Process([
            'bash', '-c',
            "git ls-remote --tags $repoUrl | grep -o 'refs/tags/[^{}]*$' | sed 's#refs/tags/##' | sort -V | tail -n1"
        ]);
        $tagProcess->run();
        if (!$tagProcess->isSuccessful()) {
            throw new \RuntimeException("Failed to fetch tags: " . $tagProcess->getErrorOutput());
        }
        $latestTag = trim($tagProcess->getOutput());

        if (!is_dir($targetPath . '/.git')) {
            echo "Cloning FrankenPHP into DIST_PATH...\n";
            $clone = new Process(['git', 'clone', $repoUrl, $targetPath]);
            $clone->run();
            if (!$clone->isSuccessful()) {
                throw new \RuntimeException("Git clone failed: " . $clone->getErrorOutput());
            }
        } else {
            echo "FrankenPHP already exists, fetching tags...\n";
            $fetch = new Process(['git', 'fetch', '--tags'], cwd: $targetPath);
            $fetch->run();
            if (!$fetch->isSuccessful()) {
                throw new \RuntimeException("Git fetch failed: " . $fetch->getErrorOutput());
            }
        }

        $checkout = new Process(['git', 'checkout', $latestTag], cwd: $targetPath);
        $checkout->run();
        if (!$checkout->isSuccessful()) {
            throw new \RuntimeException("Git checkout failed: " . $checkout->getErrorOutput());
        }

        return $latestTag;
    }
}
