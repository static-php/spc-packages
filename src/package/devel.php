<?php

namespace staticphp\package;

use staticphp\package;
use staticphp\step\CreatePackages;

class devel implements package
{
    public function getFpmConfig(): array
    {
        $phpConfigPath = BUILD_BIN_PATH . '/php-config';
        $modifiedPhpConfigPath = TEMP_DIR . '/php-config';

        $phpConfigContent = file_get_contents($phpConfigPath);

        $phpConfigContent = preg_replace(
            [
                '/^prefix=.*$/m',
                '/^ldflags=.*$/m',
                '/^libs=.*$/m',
                '/^program_prefix=.*$/m',
                '/^php_cli_binary=.*$/m',
                '#/php(?!-zts)#'
            ],
            [
                'prefix="/usr"',
                'ldflags="-lpthread"',
                'libs=""',
                'program_prefix=""',
                'php_cli_binary="php-zts"',
                '/php-zts'
            ],
            $phpConfigContent
        );
        $phpConfigContent = str_replace('libphp.so', 'libphp-zts.so', $phpConfigContent);

        file_put_contents($modifiedPhpConfigPath, $phpConfigContent);
        chmod($modifiedPhpConfigPath, 0755);

        $phpizePath = BUILD_BIN_PATH . '/phpize';
        $modifiedPhpizePath = TEMP_DIR . '/phpize';

        $phpizeContent = file_get_contents($phpizePath);
        $phpizeContent = preg_replace(
            [
                '/^prefix=.*$/m',
                '/^datarootdir=.*$/m',
            ],
            [
                'prefix="/usr"',
                'datarootdir="/php-zts"',
            ],
            $phpizeContent
        );
        $phpizeContent = str_replace(
            [
                'lib/php`',
                '"`eval echo ${prefix}/include`/php"'
            ],
            [
                'lib/php-zts',
                '"`eval echo ${prefix}/include`/php-zts"'
            ],
            $phpizeContent
        );

        file_put_contents($modifiedPhpizePath, $phpizeContent);
        chmod($modifiedPhpizePath, 0755);

        return [
            'files' => [
                $modifiedPhpConfigPath => '/usr/bin/php-config-zts',
                $modifiedPhpizePath => '/usr/bin/phpize-zts',
                BUILD_INCLUDE_PATH => '/usr/include/php-zts',
            ],
            'depends' => [
                CreatePackages::getPrefix() . '-cli',
                CreatePackages::getPrefix() . '-embed',
            ],
            'provides' => [
                'php-config-zts',
                'phpize-zts',
            ]
        ];
    }

    public function getFpmExtraArgs(): array
    {
        $phpVersion = str_replace('.', '', SPP_PHP_VERSION);
        $libName = 'lib' . CreatePackages::getPrefix() . "-$phpVersion.so";
        file_put_contents(TEMP_DIR . '/devel-postinstall.sh', "rm /usr/lib64/libphp-zts.so\nln -sf /usr/lib64/$libName /usr/lib64/libphp-zts.so");
        return ['--after-install', TEMP_DIR . '/devel-postinstall.sh'];
    }
}
