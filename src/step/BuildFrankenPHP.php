<?php

namespace staticphp\step;

use Symfony\Component\Process\Process;

class BuildFrankenPHP
{
    /**
     * Build FrankenPHP linked against the static PHP library
     *
     * @param string $phpVersion PHP version to use (e.g. '8.4')
     * @return bool True on success
     */
    public static function run(string $phpVersion): bool
    {
        echo "Building FrankenPHP linked against PHP {$phpVersion}...\n";

        // Define paths
        $phpConfigPath = BUILD_BIN_PATH . '/php-config';

        // Check if php-config exists
        if (!file_exists($phpConfigPath)) {
            echo "Error: php-config not found at {$phpConfigPath}\n";
            echo "Make sure you've built PHP first using bin/spp build\n";
            return false;
        }

        // Check if libphp.so exists
        $libPhpPath = BUILD_LIB_PATH . '/libphp.so';
        if (!file_exists($libPhpPath)) {
            echo "Error: libphp.so not found at {$libPhpPath}\n";
            echo "Make sure you've built PHP with the embed SAPI\n";
            return false;
        }

        $gitTagProcess = new Process([
            'bash', '-c',
            "git ls-remote --tags https://github.com/dunglas/frankenphp.git | grep -o 'refs/tags/[^{}]*$' | sed 's#refs/tags/##' | sort -V | tail -n1"
        ]);
        $gitTagProcess->run();
        $latestTag = trim($gitTagProcess->getOutput());

        // Execute php-config commands to get the values
        $includesProcess = new Process([$phpConfigPath, '--includes']);
        $includesProcess->run();
        $includes = trim($includesProcess->getOutput());

        $includeDirProcess = new Process([$phpConfigPath, '--include-dir']);
        $includeDirProcess->run();
        $includeDir = trim($includeDirProcess->getOutput());

        $ldflagsProcess = new Process([$phpConfigPath, '--ldflags']);
        $ldflagsProcess->run();
        $ldflags = trim($ldflagsProcess->getOutput());

        $libsProcess = new Process([$phpConfigPath, '--libs']);
        $libsProcess->run();
        $libs = trim($libsProcess->getOutput());
        $libs .= ' -lwatcher-c -lphp';

        // Set environment variables for compilation
        $env = [
            'FRANKENPHP_VERSION' => $latestTag,
            'CGO_ENABLED' => '1',
            'CGO_CFLAGS' => $includes . ' -I' . dirname($includeDir),
            'CGO_LDFLAGS' => $ldflags . ' ' . $libs . ' -Wl,-rpath,/usr/static-php/lib',
            'XCADDY_GO_BUILD_FLAGS' => "-ldflags='-w -s' -tags=nobadger,nomysql,nopgx",
            'LD_LIBRARY_PATH' => BUILD_LIB_PATH,
        ];

        // Build FrankenPHP
        echo "Compiling FrankenPHP...\n";
        $buildProcess = new Process(
            [
                'xcaddy', 'build',
                '--output', 'frankenphp',
                '--with', 'github.com/dunglas/frankenphp/caddy',
                '--with', 'github.com/dunglas/vulcain/caddy',
                '--with', 'github.com/dunglas/caddy-cbrotli',
                '--with', 'github.com/baldinof/caddy-supervisor',
                '--with', 'github.com/caddyserver/cache-handler',
            ],
            BUILD_BIN_PATH,
            $env
        );
        $buildProcess->setTimeout(null);
        $buildProcess->run(function ($type, $buffer) {
            echo $buffer;
        });

        if (!$buildProcess->isSuccessful()) {
            echo "Error compiling FrankenPHP: " . $buildProcess->getErrorOutput() . "\n";
            return false;
        }

        // Check if the binary was created
        $frankenPhpBinary = BUILD_BIN_PATH . '/frankenphp';
        if (!file_exists($frankenPhpBinary)) {
            echo "Error: FrankenPHP binary not found at {$frankenPhpBinary} after compilation\n";
            return false;
        }

        echo 'FrankenPHP successfully built at ' . BUILD_BIN_PATH . "/frankenphp\n";
        return true;
    }
}
