<?php

namespace staticphp\package;

use staticphp\package;
use staticphp\step\CreatePackages;

class embed implements package
{
    public function getFpmConfig(): array
    {
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
                '/usr/lib/static-php',
            ],
            'files' => [
                INI_PATH . '/php.ini' => '/etc/static-php/php.ini',
                BUILD_LIB_PATH . '/libphp.so' => '/usr/lib/static-php/libphp.so',
            ]
        ];
    }
}
