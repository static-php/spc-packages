<?php

namespace staticphp\package;

use staticphp\package;

class embedded implements package
{
    public function getFpmConfig(): array
    {
        return [
            'config-files' => [
                '/etc/static-php/php.ini',
            ],
            'provides' => [
                'static-php',
                'libphp.so',
            ],
            'files' => [
                __DIR__ . '/php.ini' => '/etc/static-php/php.ini',
                BUILD_LIB_PATH . '/libphp.so' => '/usr/static-php/libphp.so',
            ]
        ];

    }
}
