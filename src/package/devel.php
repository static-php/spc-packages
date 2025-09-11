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
                '/^program_suffix=.*$/m',
                '#/php(?!-zts)#'
            ],
            [
                'prefix="/usr"',
                'ldflags="-lpthread"',
                'libs=""',
                'program_prefix=""',
                'program_suffix="-zts"',
                '/php-zts'
            ],
            $phpConfigContent
        );
        $phpVersion = str_replace('.', '', SPP_PHP_VERSION);
        $libName = 'lib' . CreatePackages::getPrefix() . "-$phpVersion.so";
        $phpConfigContent = str_replace('libphp.so', $libName, $phpConfigContent);

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
                str_replace('/usr/', '', getLibdir()) . '/' . CreatePackages::getPrefix() . '`',
                '"`eval echo ${prefix}/include`/' . CreatePackages::getPrefix() . '"'
            ],
            $phpizeContent
        );

        file_put_contents($modifiedPhpizePath, $phpizeContent);
        chmod($modifiedPhpizePath, 0755);

        return [
            'files' => [
                $modifiedPhpConfigPath => '/usr/bin/php-config-zts',
                $modifiedPhpizePath => '/usr/bin/phpize-zts',
                BUILD_INCLUDE_PATH . '/php/' => '/usr/include/php-zts',
                BUILD_LIB_PATH . '/php/build' => getLibdir() . '/' . CreatePackages::getPrefix(),
            ],
            'depends' => [
                CreatePackages::getPrefix() . '-cli',
            ],
            'provides' => [
                'php-config-zts',
                'phpize-zts',
            ]
        ];
    }

    public function getFpmExtraArgs(): array
    {
        return [];
    }
}
