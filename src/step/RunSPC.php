<?php

namespace staticphp\step;

use Symfony\Component\Process\Process;

class RunSPC
{
    public static function run(string $command = 'spc', bool $debug = false, string $phpVersion = '8.4')
    {
        // Ensure the craft.yml file is copied to the static-php-cli vendor directory
        $craftYmlSource = BASE_PATH . '/config/craft.yml';
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
            $builtDir = ROOT_DIR . '/vendor/crazywhalecc/static-php-cli/buildroot';
            $movedDir = BUILD_ROOT_PATH;
            $cwd = getcwd();
            chdir(BUILD_BIN_PATH);
            exec("find . -type f -exec sed -i 's|$builtDir|$movedDir|g' {} +");
            echo "Replaced paths successfully.\n";
            chdir($cwd);

            return true;
        } catch (\Exception $e) {
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
