<?php

namespace staticphp\step;

use ArrayIterator;
use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;

class RunSPC
{
    private static function replaceInFiles(string $dir, string $builtDir, string $movedDir): void {
        if (is_dir($dir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
        }
        else {
            $files = new ArrayIterator([new SplFileInfo($dir)]);
        }

        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $contents = file_get_contents($path);

            if ($contents === false) {
                continue;
            }
            if (!str_contains($contents, $builtDir)) {
                continue;
            }

            $newContents = str_replace($builtDir, $movedDir, $contents);

            if ($newContents !== $contents) {
                file_put_contents($path, $newContents);
            }
        }
    }

    public static function run(string $command = 'spc', bool $debug = false, string $phpVersion = '8.4'): bool
    {
        // Ensure the craft.yml file is copied to the static-php-cli vendor directory
        $arch = str_contains(php_uname('m'), 'x86_64') ? 'x86_64' : 'aarch64';
        $command = SPP_COMMAND;
        $craftYmlSource = BASE_PATH . "/config/{$arch}-{$command}-craft.yml";
        $craftYmlDest = BASE_PATH . '/vendor/crazywhalecc/static-php-cli/craft.yml';

        // Read the craft.yml file
        $craftYml = file_get_contents($craftYmlSource);

        // Update the PHP version in the craft.yml content
        $craftYml = str_replace('majorminornodot', str_replace('.', '', $phpVersion), $craftYml);
        $craftYml = str_replace('majorminor', $phpVersion, $craftYml);

        // Write the updated craft.yml to the destination
        if (!file_put_contents($craftYmlDest, $craftYml)) {
            echo "Failed to write updated craft.yml to static-php-cli vendor directory.\n";
            return false;
        }

        echo "Running static-php-cli with command: {$command}...\n";
        $spcCommand = BASE_PATH . '/vendor/crazywhalecc/static-php-cli/bin/' . $command;
        passthru('chmod +x ' . $spcCommand);

        // Build the command arguments
        $args = [$spcCommand, 'craft'];
        if ($debug) {
            $args[] = '--debug';
        }

        $process = new Process($args, BASE_PATH . '/vendor/crazywhalecc/static-php-cli');
        $process->setTimeout(null); // No timeout
        $process->setTty(true); // Interactive mode

        // Run the process
        try {
            $process->mustRun(function ($type, $buffer) {
                echo $buffer;
            });

            echo "Static PHP CLI build completed successfully.\n";

            // Copy the built files to our build directory
            self::copyBuiltFiles($phpVersion);

            // Fix the prefix
            $builtDir = ROOT_DIR . '/buildroot';
            $movedDir = BUILD_ROOT_PATH;
            self::replaceInFiles(BUILD_BIN_PATH . '/php-config', $builtDir, $movedDir);
            self::replaceInFiles(BUILD_BIN_PATH . '/php-config', '/app/buildroot', $movedDir);
            self::replaceInFiles(BUILD_LIB_PATH . '/pkgconfig', $builtDir, $movedDir);
            self::replaceInFiles(BUILD_LIB_PATH . '/pkgconfig', '/app/buildroot', $movedDir);

            return true;
        } catch (Exception $e) {
            echo "Error running static-php-cli with: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private static function copyBuiltFiles(string $phpVersion): void
    {
        // Copy the built PHP binaries to our build directory
        $sourceDir = BASE_PATH . '/vendor/crazywhalecc/static-php-cli/buildroot';
        $buildDir = BUILD_ROOT_PATH;
        $baseBuildDir = BASE_PATH . '/build';

        // Check if the base build directory exists
        if (!is_dir($baseBuildDir)) {
            mkdir($baseBuildDir, 0755, true);
        }

        // Check for existing PHP versions in the build directory
        $existingVersions = [];
        if (is_dir($baseBuildDir)) {
            $dirs = scandir($baseBuildDir);
            foreach ($dirs as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir($baseBuildDir . '/' . $dir)) {
                    // Check if this directory contains a PHP binary
                    $versionBinary = $baseBuildDir . '/' . $dir . '/bin/php';
                    if (file_exists($versionBinary)) {
                        // Get the PHP version from the binary
                        $versionProcess = new Process([$versionBinary, '-r', 'echo PHP_VERSION;']);
                        $versionProcess->run();
                        $detectedVersion = trim($versionProcess->getOutput());

                        if (!empty($detectedVersion)) {
                            // Extract major.minor version
                            $parts = explode('.', $detectedVersion);
                            if (count($parts) >= 2) {
                                $majorMinor = $parts[0] . '.' . $parts[1];
                                echo "Found PHP version {$detectedVersion} (major.minor: {$majorMinor}) in directory {$dir}\n";
                                $existingVersions[$dir] = $majorMinor;
                            }
                        }
                    }
                }
            }
        }

        // Ensure the build directory exists
        if (!is_dir($buildDir)) {
            mkdir($buildDir, 0755, true);
        }

        // Clean and copy files
        exec("rm -rf {$buildDir}/*");
        exec("cp -r {$sourceDir}/* {$buildDir}");

        echo "Copied PHP {$phpVersion} files to {$buildDir}\n";
    }
}
