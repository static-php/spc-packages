<?php

namespace staticphp\step;

use ArrayIterator;
use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SPC\store\FileSystem;
use SplFileInfo;
use Symfony\Component\Process\Process;
use staticphp\util\TwigRenderer;

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

    private static function fixGnuDebugLinks(): void
    {
        $debugDir = BUILD_ROOT_PATH . '/debug';
        $binDir = BUILD_BIN_PATH;

        if (!is_dir($debugDir)) {
            echo "No debug directory found at {$debugDir}, skipping GNU debuglink normalization.\n";
            return;
        }

        $ensureRename = function (string $from, string $to) {
            if ($from === $to) {
                return;
            }
            if (file_exists($from)) {
                if (!file_exists($to)) {
                    if (!@rename($from, $to)) {
                        echo "Failed to rename {$from} -> {$to}\n";
                    } else {
                        echo "Renamed {$from} -> {$to}\n";
                    }
                } else {
                    @unlink($from);
                }
            }
        };

        $sapiMap = [
            $binDir . '/php' => $debugDir . '/php-zts.debug',
            $binDir . '/php-fpm' => $debugDir . '/php-fpm-zts.debug',
            $binDir . '/php-cgi' => $debugDir . '/php-cgi-zts.debug',
            $binDir . '/frankenphp' => $debugDir . '/frankenphp.debug',
        ];

        $ensureRename($debugDir . '/php.debug', $debugDir . '/php-zts.debug');
        $ensureRename($debugDir . '/php-fpm.debug', $debugDir . '/php-fpm-zts.debug');
        $ensureRename($debugDir . '/php-cgi.debug', $debugDir . '/php-cgi-zts.debug');

        foreach ($sapiMap as $binary => $dbgFile) {
            if (!file_exists($binary)) {
                continue;
            }
            self::runProcess(['objcopy', '--remove-gnu-debuglink', $binary], "Removed existing gnu-debuglink from {$binary}");
            if (file_exists($dbgFile)) {
                self::runProcess(['objcopy', '--add-gnu-debuglink=' . $dbgFile, $binary], "Added gnu-debuglink to {$binary} -> {$dbgFile}");
            }
        }
    }


    private static function runProcess(array $cmd, string $okMessage): void
    {
        $p = new Process($cmd);
        $p->setTimeout(null);
        $p->run();
        if ($p->isSuccessful()) {
            echo $okMessage . "\n";
        } else {
            // Log but do not fail the build
            $bin = is_array($cmd) ? implode(' ', $cmd) : (string)$cmd;
            echo "Warning: command failed: {$bin}\n" . $p->getErrorOutput() . "\n";
        }
    }

    public static function run(bool $debug = false, string $phpVersion = '8.4'): bool
    {
        $craftYmlDest = BASE_PATH . '/vendor/crazywhalecc/static-php-cli/craft.yml';

        // Render the template using the TwigRenderer
        try {
            $craftYml = TwigRenderer::renderCraftTemplate($phpVersion);

            // Write the rendered craft.yml to the destination
            if (!file_put_contents($craftYmlDest, $craftYml)) {
                echo "Failed to write updated craft.yml to static-php-cli vendor directory.\n";
                return false;
            }
        } catch (\Exception $e) {
            echo "Error rendering craft.yml template: " . $e->getMessage() . "\n";
            return false;
        }

        // Build the command arguments
        $args = ['bin/spc', 'craft'];
        if ($debug) {
            $args[] = '--debug';
        }

        $process = new Process($args, BASE_PATH . '/vendor/crazywhalecc/static-php-cli');
        $process->setTimeout(null);
        if (Process::isTtySupported()) {
            $process->setTty(true); // Interactive mode
        }

        // Run the process
        try {
            $process->mustRun(function ($type, $buffer) {
                echo $buffer;
            });

            echo "Static PHP CLI build completed successfully.\n";

            // Free up space for github runners
            if (getenv('CI') || getenv('GITHUB_ACTION')) {
                FileSystem::removeDir(BASE_PATH . '/vendor/crazywhalecc/static-php-cli/source');
                FileSystem::removeDir(BASE_PATH . '/vendor/crazywhalecc/static-php-cli/downloads');
            }

            // Copy the built files to our build directory
            self::copyBuiltFiles($phpVersion);

            // Fix the prefix
            $builtDir = BASE_PATH . '/vendor/crazywhalecc/static-php-cli/buildroot';
            $movedDir = BUILD_ROOT_PATH;
            self::replaceInFiles(BUILD_BIN_PATH . '/php-config', $builtDir, $movedDir);
            self::replaceInFiles(BUILD_BIN_PATH . '/php-config', '/app/buildroot', $movedDir);
            self::replaceInFiles(BUILD_LIB_PATH . '/pkgconfig', $builtDir, $movedDir);
            self::replaceInFiles(BUILD_LIB_PATH . '/pkgconfig', '/app/buildroot', $movedDir);

            // After files are copied and paths fixed, normalize GNU debug links
            self::fixGnuDebugLinks();

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

        // Create the base build directory if it doesn't exist
        if (!is_dir($baseBuildDir) && !mkdir($baseBuildDir, 0755, true) && !is_dir($baseBuildDir)) {
            echo "Failed to create directory: {$baseBuildDir}\n";
            return;
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

        // Create the build directory if it doesn't exist
        if (!is_dir($buildDir) && !mkdir($buildDir, 0755, true) && !is_dir($buildDir)) {
            echo "Failed to create directory: {$buildDir}\n";
            return;
        }

        // Clean and copy files
        exec("rm -rf {$buildDir}/*");
        exec("cp -r {$sourceDir}/* {$buildDir}");

        echo "Copied PHP {$phpVersion} files to {$buildDir}\n";
    }
}
