<?php

namespace staticphp\step;

use Symfony\Component\Process\Process;

class RunSPC
{
    public static function run(string $command = 'spc', bool $debug = false)
    {
        // Ensure the craft.yml file is copied to the static-php-cli vendor directory
        $craftYmlSource = BASE_PATH . '/config/craft.yml';
        $craftYmlDest = BASE_PATH . '/vendor/crazywhalecc/static-php-cli/craft.yml';

        if (!copy($craftYmlSource, $craftYmlDest)) {
            echo "Failed to copy craft.yml to static-php-cli vendor directory.\n";
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
            self::copyBuiltFiles();

            return true;
        } catch (\Exception $e) {
            echo "Error running static-php-cli with: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private static function copyBuiltFiles(): void
    {
        // Copy the built PHP binaries to our build directory
        $sourceDir = BASE_PATH . '/vendor/crazywhalecc/static-php-cli/buildroot';
        $buildDir = BUILD_ROOT_PATH;

        // Ensure the build directory exists
        if (!is_dir($buildDir)) {
            mkdir($buildDir, 0755, true);
        }

        // Clean and copy files
        exec("rm -rf {$buildDir}/*");
        exec("cp -r {$sourceDir}/* {$buildDir}");
    }
}
