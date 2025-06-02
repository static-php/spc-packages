<?php

namespace staticphp\step;

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class CreatePackages
{
    private static $craftConfig;
    private static $extensions = [];
    private static $sharedExtensions = [];
    private static $sapis = [];

    public static function run()
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

    private static function loadConfig()
    {
        $craftYmlPath = BASE_PATH . '/config/craft.yml';
        echo "Loading configuration from {$craftYmlPath}...\n";

        if (!file_exists($craftYmlPath)) {
            throw new \RuntimeException("Configuration file not found: {$craftYmlPath}");
        }

        self::$craftConfig = Yaml::parseFile($craftYmlPath);

        // Get the list of extensions
        if (isset(self::$craftConfig['extensions'])) {
            $extensions = self::$craftConfig['extensions'];
            if (is_string($extensions)) {
                $extensions = explode(',', $extensions);
            }
            self::$extensions = array_map('trim', $extensions);
        }

        // Get the list of shared extensions
        if (isset(self::$craftConfig['build-options']['build-shared'])) {
            $sharedExtensions = self::$craftConfig['build-options']['build-shared'];
            if (is_string($sharedExtensions)) {
                $sharedExtensions = explode(',', $sharedExtensions);
            }
            self::$sharedExtensions = array_map('trim', $sharedExtensions);
        }

        // Get the list of SAPIs
        if (isset(self::$craftConfig['sapi'])) {
            $sapis = self::$craftConfig['sapi'];
            if (is_string($sapis)) {
                $sapis = explode(',', $sapis);
            }
            self::$sapis = array_map('trim', $sapis);
        }

        echo "Loaded configuration:\n";
        echo "- SAPIs: " . implode(', ', self::$sapis) . "\n";
        echo "- Extensions: " . implode(', ', self::$extensions) . "\n";
        echo "- Shared Extensions: " . implode(', ', self::$sharedExtensions) . "\n";
    }

    private static function createSapiPackages()
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

            // Create RPM package
            self::createRpmPackage($sapi, $config);

            // Create DEB package
            self::createDebPackage($sapi, $config);
        }
    }

    private static function createExtensionPackages()
    {
        echo "Creating packages for extensions...\n";

        // Combine both extension lists
        $allExtensions = array_merge(self::$extensions, self::$sharedExtensions);
        $allExtensions = array_unique($allExtensions);

        foreach ($allExtensions as $extension) {
            echo "Creating package for extension: {$extension}...\n";

            // Create a package for this extension
            $package = new \staticphp\extension();
            $config = $package->getFpmConfig();

            // Create RPM package
            self::createRpmPackage("ext-{$extension}", $config);

            // Create DEB package
            self::createDebPackage("ext-{$extension}", $config);
        }
    }

    private static function createRpmPackage($name, $config)
    {
        echo "Creating RPM package for {$name}...\n";

        // Create a temporary directory for the package
        $tempDir = sys_get_temp_dir() . "/spc-package-{$name}-" . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            // Create the package structure
            self::createPackageStructure($tempDir, $config);

            // Create the RPM spec file
            $specFile = self::createRpmSpecFile($tempDir, $name, $config);

            // Build the RPM package
            $rpmProcess = new Process([
                'rpmbuild',
                '-bb',
                $specFile,
                '--define', "_topdir {$tempDir}",
                '--define', "buildroot {$tempDir}/BUILDROOT"
            ]);
            $rpmProcess->setTimeout(null);
            $rpmProcess->run(function ($type, $buffer) {
                echo $buffer;
            });

            // Copy the RPM package to the dist directory
            $rpmFile = glob("{$tempDir}/RPMS/*/{$name}*.rpm")[0] ?? null;
            if ($rpmFile && file_exists($rpmFile)) {
                $destFile = DIST_RPM_PATH . '/' . basename($rpmFile);
                copy($rpmFile, $destFile);
                echo "RPM package created: {$destFile}\n";
            } else {
                echo "Warning: RPM package not found\n";
            }
        } finally {
            // Clean up the temporary directory
            self::removeDirectory($tempDir);
        }
    }

    private static function createDebPackage($name, $config)
    {
        echo "Creating DEB package for {$name}...\n";

        // Create a temporary directory for the package
        $tempDir = sys_get_temp_dir() . "/spc-package-{$name}-" . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            // Create the package structure
            self::createPackageStructure($tempDir, $config);

            // Create the Debian control file
            $controlDir = "{$tempDir}/DEBIAN";
            mkdir($controlDir, 0755, true);

            $controlFile = "{$controlDir}/control";
            $controlContent = self::createDebControlFile($name, $config);
            file_put_contents($controlFile, $controlContent);

            // Build the DEB package
            $debProcess = new Process([
                'dpkg-deb',
                '--build',
                $tempDir,
                DIST_DEB_PATH . "/{$name}.deb"
            ]);
            $debProcess->setTimeout(null);
            $debProcess->run(function ($type, $buffer) {
                echo $buffer;
            });

            echo "DEB package created: " . DIST_DEB_PATH . "/{$name}.deb\n";
        } finally {
            // Clean up the temporary directory
            self::removeDirectory($tempDir);
        }
    }

    private static function createPackageStructure($tempDir, $config)
    {
        // Create directories
        mkdir("{$tempDir}/SPECS", 0755, true);
        mkdir("{$tempDir}/SOURCES", 0755, true);
        mkdir("{$tempDir}/BUILD", 0755, true);
        mkdir("{$tempDir}/BUILDROOT", 0755, true);
        mkdir("{$tempDir}/RPMS", 0755, true);
        mkdir("{$tempDir}/SRPMS", 0755, true);

        // Copy files
        if (isset($config['files']) && is_array($config['files'])) {
            foreach ($config['files'] as $source => $dest) {
                $destPath = "{$tempDir}/BUILDROOT{$dest}";
                $destDir = dirname($destPath);

                if (!file_exists($destDir)) {
                    mkdir($destDir, 0755, true);
                }

                if (file_exists($source)) {
                    copy($source, $destPath);
                    echo "Copied {$source} to {$destPath}\n";
                } else {
                    echo "Warning: Source file not found: {$source}\n";
                }
            }
        }
    }

    private static function createRpmSpecFile($tempDir, $name, $config)
    {
        $specFile = "{$tempDir}/SPECS/{$name}.spec";

        $provides = isset($config['provides']) ? implode(', ', $config['provides']) : '';
        $depends = isset($config['depends']) ? implode(', ', $config['depends']) : '';
        $configFiles = isset($config['config-files']) ? implode(' ', $config['config-files']) : '';

        $specContent = <<<EOT
Name: {$name}
Version: 1.0.0
Release: 1
Summary: Static PHP Package for {$name}
License: MIT
BuildArch: x86_64

EOT;

        if (!empty($provides)) {
            $specContent .= "Provides: {$provides}\n";
        }

        if (!empty($depends)) {
            $specContent .= "Requires: {$depends}\n";
        }

        $specContent .= <<<EOT

%description
Static PHP Package for {$name}

%files
EOT;

        if (!empty($configFiles)) {
            $specContent .= "\n%config {$configFiles}";
        }

        if (isset($config['files']) && is_array($config['files'])) {
            foreach ($config['files'] as $source => $dest) {
                $specContent .= "\n{$dest}";
            }
        }

        file_put_contents($specFile, $specContent);
        return $specFile;
    }

    private static function createDebControlFile($name, $config)
    {
        $provides = isset($config['provides']) ? implode(', ', $config['provides']) : '';
        $depends = isset($config['depends']) ? implode(', ', $config['depends']) : '';

        $controlContent = <<<EOT
Package: {$name}
Version: 1.0.0-1
Section: web
Priority: optional
Architecture: amd64
Maintainer: Static PHP <info@static-php.dev>
EOT;

        if (!empty($depends)) {
            $controlContent .= "\nDepends: {$depends}";
        }

        if (!empty($provides)) {
            $controlContent .= "\nProvides: {$provides}";
        }

        $controlContent .= "\nDescription: Static PHP Package for {$name}";

        return $controlContent;
    }

    private static function removeDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "{$dir}/{$file}";
            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
