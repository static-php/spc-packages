<?php

namespace staticphp\package;

use staticphp\package;
use staticphp\step\CreatePackages;

class embed implements package
{
    public function getFpmConfig(): array
    {
        $phpVersion = SPP_PHP_VERSION;
        $phpVersion = str_replace('.', '', $phpVersion);
        $name = '/lib' . CreatePackages::getPrefix() . "-$phpVersion.so";
        return [
            'config-files' => [
                '/etc/static-php/php.ini',
            ],
            'depends' => [
                CreatePackages::getPrefix() . '-cli',
            ],
            'provides' => [
                'libphp.so'
            ],
            'directories' => [
                '/usr/lib64/php-zts',
            ],
            'files' => [
                INI_PATH . '/php.ini' => '/etc/php-zts.ini',
                BUILD_LIB_PATH . $name => '/usr/lib64/' . $name,
            ]
        ];
    }
}
