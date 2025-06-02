<?php

namespace staticphp\step;

use Symfony\Component\Process\Process;

class RunSPC
{
    public static function run()
    {
        // Ensure the craft.yml file is copied to the static-php-cli vendor directory
        $craftYmlSource = BASE_PATH . '/config/craft.yml';
        $craftYmlDest = BASE_PATH . '/vendor/crazywhalecc/static-php-cli/craft.yml';

        if (!copy($craftYmlSource, $craftYmlDest)) {
            echo "Failed to copy craft.yml to static-php-cli vendor directory.\n";
            return false;
        }

        echo "Running static-php-cli...\n";
        $command = 'spc-gnu-docker';
        $spcCommand = BASE_PATH . '/vendor/crazywhalecc/static-php-cli/bin/' . $command;
        passthru('chmod +x ' . $spcCommand);

        $process = new Process([$spcCommand, 'craft', '--debug'], BASE_PATH . '/vendor/crazywhalecc/static-php-cli');
        $process->setTimeout(null); // No timeout
        $process->setTty(true); // Interactive mode

        // Run the process
        try {
            $process->mustRun(function ($type, $buffer) {
                echo $buffer;
            });

            echo "Static PHP CLI build completed successfully.\n";

            // Copy the built files to our build directory
            self::copyBuiltFiles();

            return true;
        } catch (\Exception $e) {
            echo "Error running static-php-cli with: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private static function copyBuiltFiles()
    {
        // Copy the built PHP binaries to our build directory
        $sourceDir = BASE_PATH . '/vendor/crazywhalecc/static-php-cli/build/output';

        // Find and copy PHP binaries
        self::copyBinaries($sourceDir, BUILD_BIN_PATH);

        // Find and copy PHP modules
        self::copyModules($sourceDir, BUILD_MODULES_PATH);

        // Find and copy PHP libraries
        self::copyLibraries($sourceDir, BUILD_LIB_PATH);

        echo "Built files copied to build directory.\n";
    }

    private static function copyBinaries($sourceDir, $destDir)
    {
        // Copy PHP binaries (php, php-fpm, etc.)
        $binaries = glob($sourceDir . '/bin/php*');
        foreach ($binaries as $binary) {
            $filename = basename($binary);
            copy($binary, $destDir . '/' . $filename);
            chmod($destDir . '/' . $filename, 0755);
            echo "Copied binary: $filename\n";
        }
    }

    private static function copyModules($sourceDir, $destDir)
    {
        // Copy PHP modules (.so files)
        $modules = glob($sourceDir . '/lib/php/extensions/*/*/*.so');
        foreach ($modules as $module) {
            $filename = basename($module);
            copy($module, $destDir . '/' . $filename);
            echo "Copied module: $filename\n";
        }
    }

    private static function copyLibraries($sourceDir, $destDir)
    {
        // Copy PHP libraries (.so files)
        $libraries = glob($sourceDir . '/lib/libphp*.so');
        foreach ($libraries as $library) {
            $filename = basename($library);
            copy($library, $destDir . '/' . $filename);
            echo "Copied library: $filename\n";
        }
    }
}
